<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Bootstrap da suíte de testes
|--------------------------------------------------------------------------
|
| Dentro do container, as variáveis do env_file do docker-compose chegam ao
| PHP também pelo $_SERVER. O repositório de env do Laravel consulta
| $_SERVER ANTES de $_ENV, então o bloco <env> do phpunit.xml não vencia esses
| valores — nem com force="true", que popula apenas $_ENV/putenv.
|
| Na prática a suíte inteira rodava com a configuração de desenvolvimento:
| banco nexo_crm (o de dev, apagado a cada RefreshDatabase), fila no Redis
| (jobs enfileirados e nunca processados durante o teste) e cache Redis
| compartilhado entre execuções.
|
| Por isso o ambiente de teste é carregado aqui, de .env.testing, escrevendo
| nas três fontes ($_SERVER, $_ENV e putenv) para que nenhuma sobra do
| ambiente do container escape.
|
*/

require __DIR__.'/../vendor/autoload.php';

$variables = Dotenv\Dotenv::createArrayBacked(dirname(__DIR__), '.env.testing')->load();

foreach ($variables as $key => $value) {
    $value = (string) $value;

    $_SERVER[$key] = $value;
    $_ENV[$key] = $value;
    putenv("{$key}={$value}");
}
