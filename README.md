# nexo-crm-api

Backend do Nexo CRM — SaaS de CRM B2B multi-tenant. Laravel 12 sobre PostgreSQL e Redis, expondo uma REST API autenticada por JWT.

É o **único** componente com acesso ao banco. O frontend (`nexo-crm-app`) fala exclusivamente por HTTP.

---

## Stack

| Peça | Escolha | Por quê |
|---|---|---|
| Framework | Laravel 12 (PHP 8.4) | — |
| Banco | PostgreSQL 16 | `jsonb`, constraints e índices parciais; SQLite não dá conta do schema |
| Cache/filas | Redis 7 (via Predis) | Predis evita depender da extensão `phpredis` na imagem |
| Auth | `php-open-source-saver/jwt-auth` | fork mantido do `tymon/jwt-auth` |
| Testes | Pest 3 | |
| Docs | `darkaonline/l5-swagger` | OpenAPI 3 em `/api/documentation` |
| Estilo | Laravel Pint | |

Identificadores são **UUID v7** em todo o projeto (trait `HasUuidV7`): ordenáveis por tempo como um autoincrement, sem revelar volume de negócio nem permitir enumeração.

---

## Subindo o ambiente

Tudo roda em Docker; não é preciso ter PHP na máquina. Os comandos abaixo saem da **raiz do repositório** (um nível acima desta pasta).

```bash
cp .env.example .env
docker compose up -d
docker compose exec nexo-crm-api php artisan migrate --seed
```

| Serviço | Endereço |
|---|---|
| API | http://localhost:8090 |
| Swagger UI | http://localhost:8090/api/documentation |
| Frontend | http://localhost:4200 |
| pgAdmin | http://localhost:5050 |

> **Alterou o `.env`?** Rode `docker compose up -d --force-recreate`. As variáveis são fixadas na criação do container — um `restart` continua com os valores antigos.

---

## Comandos do dia a dia

```bash
# Testes (119 casos)
docker compose exec nexo-crm-api php artisan test
docker compose exec nexo-crm-api php artisan test --filter=Leads

# Estilo
docker compose exec nexo-crm-api ./vendor/bin/pint          # corrige
docker compose exec nexo-crm-api ./vendor/bin/pint --test   # só verifica

# Documentação OpenAPI
docker compose exec nexo-crm-api php artisan l5-swagger:generate

# Banco
docker compose exec nexo-crm-api php artisan migrate:fresh --seed
```

---

## Estrutura

```text
app/
├── Enums/            # conjuntos fechados (status, papéis, períodos) — nunca string mágica
├── Events/           # eventos de domínio (OpportunityWon, TaskAssigned, EventScheduled)
├── Listeners/        # efeitos colaterais desacoplados
├── Http/
│   ├── Controllers/Api/V1/   # finos: validam via Form Request, delegam, devolvem Resource
│   ├── Middleware/           # ResolveTenantContext, SecurityHeaders
│   ├── Requests/             # toda validação de entrada
│   └── Resources/            # toda formatação de saída
├── Models/
│   ├── Concerns/     # BelongsToTenant, HasUuidV7
│   └── Scopes/       # TenantScope
├── Notifications/    # e-mail + canal in-app próprio
├── OpenApi/          # spec base e schemas (atributos PHP 8)
├── Policies/         # autorização por recurso
├── Rules/            # TenantScopedRules
└── Services/         # regra de negócio e orquestração
```

---

## Isolamento entre tenants

A regra mais importante do produto. Vazar dado entre tenants é a pior falha possível aqui.

**O tenant vem da identidade autenticada, nunca do request.** O `ResolveTenantContext` lê o claim `tenant_id` do JWT e **revalida a membership a cada requisição** — revogar o acesso de alguém tem efeito imediato, sem esperar o token expirar.

**A `TenantScope` falha fechada.** Sem tenant resolvido, a query vira `where 1 = 0` em vez de devolver tudo. Ela não abre exceção para console: os testes rodam via CLI, e uma exceção ali desligaria a regra exatamente onde ela precisa ser provada.

**Recurso de outro tenant responde 404, não 403** — um 403 confirmaria que o registro existe.

### Onde a proteção automática não alcança

Três situações em que a `TenantScope` **não** protege, e a checagem manual é a única barreira:

1. **Models sem a trait** — `Membership`, `AuditLog`, `Notification`, `PipelineStage`, `EventGuest`, `TaskChecklistItem`. Uns precisam de consulta cross-tenant legítima (login), outros se escopam pelo pai.
2. **Recursos aninhados** — uma etapa resolvida por ID pode ser de outro pipeline. Daí os `abort_unless($filho->pai_id === $pai->id, 404)`.
3. **Validação `exists`** — `exists:tabela,coluna` consulta a tabela **direto**, sem passar pelos scopes do Eloquent. Toda FK precisa de filtro explícito de tenant; use `App\Rules\TenantScopedRules`.

O caso mais fácil de errar é o de usuário: `users` é global (uma pessoa pode estar em várias empresas), então a FK não diz nada sobre tenant. O vínculo real vive em `memberships` — é contra ela que `owner_id` e afins são validados.

---

## Autenticação

Access token JWT de vida curta (claims de `tenant_id` e papel) + refresh token opaco.

O refresh token **não volta no corpo da resposta**: vai num cookie `httpOnly` restrito a `/api/v1/auth`, guardado no banco como hash SHA-256 e **rotacionado a cada refresh**. Logout e troca de senha revogam as sessões.

Quem pertence a mais de uma empresa recebe no login um `requires_tenant_selection` e um token sem claim de tenant, que só serve para chamar `/auth/select-tenant`.

---

## RBAC

Cinco papéis (Super Admin, Admin, Manager, Sales, Support) sobre um catálogo de **60 permissões** (10 módulos × 6 ações), seed fixo do produto — não configurável por tenant.

As permissões são resolvidas dinamicamente por um `Gate::before`, sem um `Gate::define()` por permissão. **A ordem dentro dele importa:** a checagem de slug vem antes do atalho de Super Admin. Um `Gate::before` que devolvesse `true` para qualquer ability curto-circuitaria as Policies inteiras — e com elas a checagem de tenant que, nos models sem `TenantScope`, é a única que existe.

Toda Policy de recurso valida **tenant antes de papel**.

---

## Convenções que economizam tempo

**Dinheiro é sempre inteiro em centavos** (`*_cents`). Nunca float.

**Datas em UTC**, convertidas só na borda de apresentação.

**Trabalho pesado vai para fila.** As Notifications carregam apenas escalares, nunca models: um model com `TenantScope` não sobrevive à volta da fila — o worker não tem tenant resolvido e o scope, que falha fechado, devolveria "não encontrado".

**Efeitos colaterais são despachados fora da transação.** Notificar sobre uma venda que sofreu rollback é pior do que não notificar.

**Listeners são auto-descobertos.** Não registre com `Event::listen` — o Laravel 12 já descobre listeners em `app/Listeners`, e o registro manual soma à descoberta, fazendo o handler rodar duas vezes. Confira com `php artisan event:list`.

**Uploads** vão para storage privado, com nome gerado e extensão derivada do **conteúdo** (nunca do nome enviado). O download sai por URL assinada temporária, sempre como `attachment` — servir conteúdo de usuário `inline` na origem da API o transformaria em XSS com acesso ao cookie de sessão.

---

## Testes

```bash
docker compose exec nexo-crm-api php artisan test
```

O ambiente de teste vive em `.env.testing`, aplicado por `tests/bootstrap.php`. **Isso não é decoração:** o bloco `<env>` do `phpunit.xml` não funciona dentro do container, porque as variáveis do `docker-compose` chegam pelo `$_SERVER`, que o Laravel lê antes do `$_ENV` — nem com `force="true"`. Sem esse bootstrap, a suíte roda contra o banco de **desenvolvimento** e o apaga a cada `RefreshDatabase`.

Prioridade de cobertura, nesta ordem: autenticação → isolamento de tenant → RBAC → Policies → CRUD → contrato de API.

Uma funcionalidade não está pronta só porque funciona manualmente: precisa de teste do caminho principal e, no mínimo, do acesso indevido (tenant errado, papel errado).

Helpers em `tests/Pest.php`:

```php
['token' => $token, 'tenant' => $tenant, 'user' => $user] = actingAsTenantUser('admin');
memberOf($user, $tenant, 'sales');
```

---

## Documentação da API

Swagger UI em `/api/documentation` — 79 operações, 56 schemas.

O spec é escrito em **atributos PHP 8**, não em docblocks: o swagger-php 6 removeu o suporte a anotações `@OA` em comentário. A base fica em `app/OpenApi/`; as operações, nos próprios controllers.

`storage/api-docs/` é gerado e não versionado. Em desenvolvimento, `L5_SWAGGER_GENERATE_ALWAYS=true` mantém o spec sempre em dia.

---

## Antes de abrir PR

1. `./vendor/bin/pint`
2. `php artisan test`
3. `php artisan l5-swagger:generate` se mexeu em endpoint
4. Reveja sob a ótica de isolamento de tenant e segurança — as seções 5 e 9 do [`CLAUDE.md`](../CLAUDE.md)

Pergunta que vale fazer sempre: *se alguém trocar esse ID pelo de outro tenant, o que acontece?*
