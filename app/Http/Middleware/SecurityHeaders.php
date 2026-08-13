<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Headers de segurança em toda resposta da API (CLAUDE.md §9).
 *
 * O mais importante aqui é o nosniff: a API serve arquivos enviados por
 * usuários (rota de download assinada) a partir da mesma origem que guarda o
 * cookie de refresh. Sem ele, o navegador pode adivinhar o tipo do conteúdo e
 * executar como HTML algo que deveria ser só um anexo.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        // HSTS só faz sentido (e só é honrado) sobre HTTPS; em dev o docker
        // serve HTTP puro.
        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
