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

class InviteController extends Controller
{
    /**
     * Consome o convite: reaproveita o mesmo mecanismo de token do reset de
     * senha (Password broker) — um convite é, na prática, "defina sua senha
     * pela primeira vez usando um token de uso único enviado por e-mail".
     * A criação do convite (User + Membership status=invited) é feita pela
     * feature de gestão de usuários (Fase 8), não por este endpoint.
     */
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
