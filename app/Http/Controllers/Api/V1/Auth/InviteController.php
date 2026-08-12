<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\MembershipStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AcceptInviteRequest;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class InviteController extends Controller
{
    /**
     * Consome o convite: reaproveita o mesmo mecanismo de token do reset de
     * senha (Password broker) — um convite é, na prática, "defina sua senha
     * pela primeira vez usando um token de uso único enviado por e-mail".
     * A criação do convite (User + Membership status=invited) é feita pela
     * feature de gestão de usuários (Fase 8), não por este endpoint.
     */
    #[OA\Post(
        path: '/api/v1/auth/invites/accept',
        summary: 'Aceita um convite e define a senha',
        description: 'Fecha o fluxo iniciado em `POST /members`: define a senha do usuário convidado e ativa a membership.',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['token', 'email', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'token', description: 'Token do link de convite', type: 'string'),
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 8),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password'),
                ],
            ),
        ),
        tags: ['Autenticação'],
        responses: [
            new OA\Response(response: 200, description: 'Convite aceito', content: new OA\JsonContent(ref: '#/components/schemas/ErrorMessage')),
            new OA\Response(response: 422, description: 'Token inválido/expirado, ou nenhum convite pendente para o e-mail', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
            new OA\Response(response: 429, description: 'Rate limit excedido', content: new OA\JsonContent(ref: '#/components/schemas/ErrorMessage')),
        ],
    )]
    public function accept(AcceptInviteRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ])->save();

                Membership::query()
                    ->where('user_id', $user->id)
                    ->where('status', MembershipStatus::Invited)
                    ->update(['status' => MembershipStatus::Active, 'joined_at' => now()]);
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'token' => ['Convite inválido ou expirado.'],
            ]);
        }

        return response()->json([
            'data' => ['message' => 'Convite aceito. Você já pode fazer login.'],
        ]);
    }
}
