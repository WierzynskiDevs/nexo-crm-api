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

class PasswordResetController extends Controller
{
    public function __construct(private readonly TokenService $tokens) {}

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        // Resposta sempre genérica: não revela se o e-mail existe na base.
        Password::sendResetLink($request->only('email'));

        return response()->json([
            'data' => ['message' => 'Se o e-mail existir, enviaremos instruções de redefinição de senha.'],
        ]);
    }

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
