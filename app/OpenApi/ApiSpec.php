<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/**
 * Raiz do documento OpenAPI: metadados, servidor, esquema de segurança e as
 * respostas/parâmetros reaproveitados por toda a API.
 *
 * Fica numa classe própria (e não no Controller base) para que a definição do
 * contrato não se misture com código executável — nada aqui roda.
 *
 * Usamos atributos PHP 8, não docblocks: o swagger-php 6 removeu o suporte a
 * anotações em docblock.
 */
#[OA\Info(
    version: '1.0.0',
    title: 'Nexo CRM API',
    description: <<<'MD'
        API REST do Nexo CRM — SaaS de CRM B2B multi-tenant.

        ## Autenticação
        Os endpoints de domínio exigem um access token JWT em
        `Authorization: Bearer <token>`. O token carrega as claims de tenant e
        papel, e é obtido em `POST /api/v1/auth/login`.

        O refresh token **não** é devolvido no corpo: ele vai num cookie
        httpOnly restrito a `/api/v1/auth`, e é rotacionado a cada
        `POST /api/v1/auth/refresh`.

        ## Isolamento entre tenants
        O tenant corrente é sempre derivado da identidade autenticada, nunca de
        parâmetro enviado pelo cliente. Um recurso de outro tenant responde
        **404** (e não 403), para não confirmar a existência do registro.

        ## Valores monetários
        Sempre inteiros em centavos (campos `*_cents`). Nunca ponto flutuante.

        ## Datas
        Sempre ISO 8601 em UTC. A conversão para o fuso do usuário é
        responsabilidade do cliente.
        MD,
)]
// URL literal do ambiente local. Para publicar o spec apontando para
// staging/produção, trocar por L5_SWAGGER_CONST_HOST (constante que o
// l5-swagger define a partir do .env) — não usamos ainda porque a
// substituição de constante em atributo PHP não foi validada aqui.
#[OA\Server(url: 'http://localhost:8090', description: 'Ambiente local (Docker)')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
    description: 'Access token JWT devolvido pelo login.',
)]
#[OA\Tag(name: 'Autenticação', description: 'Registro, login, refresh e sessão')]
#[OA\Tag(name: 'Dashboard', description: 'Indicadores agregados do tenant')]
#[OA\Tag(name: 'Leads', description: 'Funil de entrada')]
#[OA\Tag(name: 'Clientes', description: 'Contas já convertidas')]
#[OA\Tag(name: 'Pipelines', description: 'Funis e suas etapas')]
#[OA\Tag(name: 'Oportunidades', description: 'Negociações em andamento')]
#[OA\Tag(name: 'Tarefas', description: 'Quadro de tarefas e checklists')]
#[OA\Tag(name: 'Agenda', description: 'Eventos e convidados')]
#[OA\Tag(name: 'Arquivos', description: 'Upload e download assinado')]
#[OA\Tag(name: 'Governança', description: 'Membros, equipes, papéis e auditoria')]
#[OA\Tag(name: 'Notificações', description: 'Caixa de notificações do usuário')]
#[OA\Response(
    response: 'Unauthorized',
    description: 'Token ausente, inválido ou expirado',
    content: new OA\JsonContent(ref: '#/components/schemas/ErrorMessage'),
)]
#[OA\Response(
    response: 'Forbidden',
    description: 'Autenticado, mas sem permissão para a ação',
    content: new OA\JsonContent(ref: '#/components/schemas/ErrorMessage'),
)]
#[OA\Response(
    response: 'NotFound',
    description: 'Recurso inexistente — ou pertencente a outro tenant',
    content: new OA\JsonContent(ref: '#/components/schemas/ErrorMessage'),
)]
#[OA\Response(
    response: 'ValidationError',
    description: 'Payload inválido',
    content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'),
)]
#[OA\Response(response: 'NoContent', description: 'Executado com sucesso, sem corpo de resposta')]
#[OA\Parameter(
    parameter: 'page',
    name: 'page',
    description: 'Página da coleção',
    in: 'query',
    schema: new OA\Schema(type: 'integer', minimum: 1, default: 1),
)]
#[OA\Parameter(
    parameter: 'perPage',
    name: 'per_page',
    description: 'Itens por página',
    in: 'query',
    schema: new OA\Schema(type: 'integer', maximum: 100, minimum: 1),
)]
final class ApiSpec {}
