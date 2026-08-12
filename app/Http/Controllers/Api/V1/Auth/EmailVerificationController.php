<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

class EmailVerificationController extends Controller
{
    /**
     * A rota já valida assinatura/expiração via middleware "signed" — aqui só
     * confere se o hash corresponde ao e-mail atual do usuário.
     */
    #[OA\Post(
        path: '/api/v1/auth/email/verify/{id}/{hash}',
        summary: 'Confirma o e-mail a partir do link assinado',
        description: 'Chamado pelo frontend repassando os parâmetros do link enviado por e-mail. A assinatura é a autorização — não exige Bearer token.',
        tags: ['Autenticação'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'hash', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'expires', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'signature', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'E-mail verificado (ou já verificado antes)', content: new OA\JsonContent(ref: '#/components/schemas/ErrorMessage')),
            new OA\Response(response: 403, description: 'Assinatura inválida/expirada ou hash não confere', content: new OA\JsonContent(ref: '#/components/schemas/ErrorMessage')),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function verify(Request $request, string $id, string $hash): JsonResponse
    {
        $user = User::query()->findOrFail($id);

        if (! hash_equals($hash, sha1($user->getEmailForVerification()))) {
            return response()->json(['message' => 'Link de verificação inválido.'], 403);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return response()->json([
            'data' => ['message' => 'E-mail verificado com sucesso.'],
        ]);
    }

    #[OA\Post(
        path: '/api/v1/auth/email/resend',
        summary: 'Reenvia o e-mail de verificação',
        security: [['bearerAuth' => []]],
        tags: ['Autenticação'],
        responses: [
            new OA\Response(response: 200, description: 'Reenviado (ou e-mail já verificado)', content: new OA\JsonContent(ref: '#/components/schemas/ErrorMessage')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 429, description: 'Rate limit excedido', content: new OA\JsonContent(ref: '#/components/schemas/ErrorMessage')),
        ],
    )]
    public function resend(Request $request): JsonResponse
    {
        $user = Auth::guard('api')->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'data' => ['message' => 'E-mail já verificado.'],
            ]);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'data' => ['message' => 'E-mail de verificação reenviado.'],
        ]);
    }
}
