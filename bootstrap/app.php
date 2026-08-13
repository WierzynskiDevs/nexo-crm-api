<?php

use App\Http\Middleware\ResolveTenantContext;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->throttleApi();

        $middleware->api(prepend: [SecurityHeaders::class]);

        $middleware->alias([
            'tenant' => ResolveTenantContext::class,
        ]);

        /**
         * SubstituteBindings resolve os {model} das rotas (ex.: {lead}) — se
         * rodar antes de "auth:api"/"tenant", o binding tenta buscar o
         * registro sem nenhum tenant resolvido ainda, e a TenantScope falha
         * fechada (404 para tudo, mesmo recursos do próprio tenant). Por
         * isso ela é removida do grupo "api" padrão e reaplicada
         * explicitamente DEPOIS de "auth:api"/"tenant" em routes/api.php.
         */
        $middleware->api(remove: [SubstituteBindings::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
