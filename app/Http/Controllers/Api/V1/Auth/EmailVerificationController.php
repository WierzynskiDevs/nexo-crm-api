<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EmailVerificationController extends Controller
{
    /**
     * A rota já valida assinatura/expiração via middleware "signed" — aqui só
     * confere se o hash corresponde ao e-mail atual do usuário.
     */
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
