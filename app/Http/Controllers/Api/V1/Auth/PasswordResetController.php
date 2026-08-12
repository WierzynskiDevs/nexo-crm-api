<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Services\Auth\TokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class PasswordResetController extends Controller
{
    public function __construct(private readonly TokenService $tokens) {}

    #[OA\Post(
        path: '/api/v1/auth/forgot-password',
        summary: 'Solicita o e-mail de redefinição de senha',
        description: 'Responde sempre a mesma mensagem genérica, exista ou não o e-mail — não confirmar quais endereços estão cadastrados é intencional.',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(required: ['email'], properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email'),
            ]),
        ),
        tags: ['Autenticação'],
        responses: [
            new OA\Response(response: 200, description: 'Resposta genérica de confirmação', content: new OA\JsonContent(ref: '#/components/schemas/ErrorMessage')),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
            new OA\Response(response: 429, description: 'Rate limit excedido', content: new OA\JsonContent(ref: '#/components/schemas/ErrorMessage')),
        ],
    )]
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        // Resposta sempre genérica: não revela se o e-mail existe na base.
        Password::sendResetLink($request->only('email'));

        return response()->json([
            'data' => ['message' => 'Se o e-mail existir, enviaremos instruções de redefinição de senha.'],
        ]);
    }

    #[OA\Post(
        path: '/api/v1/auth/reset-password',
        summary: 'Redefine a senha com o token recebido por e-mail',
        description: 'Ao trocar a senha, todas as sessões de refresh existentes do usuário são revogadas.',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['token', 'email', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'token', type: 'string'),
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 8),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password'),
                ],
            ),
        ),
        tags: ['Autenticação'],
        responses: [
            new OA\Response(response: 200, description: 'Senha redefinida', content: new OA\JsonContent(ref: '#/components/schemas/ErrorMessage')),
            new OA\Response(response: 422, description: 'Token inválido/expirado ou payload inválido', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
            new OA\Response(response: 429, description: 'Rate limit excedido', content: new OA\JsonContent(ref: '#/components/schemas/ErrorMessage')),
        ],
    )]
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => Hash::make($password)])->save();
                $this->tokens->revokeAllForUser($user);
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => ['Token inválido ou expirado.'],
            ]);
        }

        return response()->json([
            'data' => ['message' => 'Senha redefinida com sucesso.'],
        ]);
    }
}
