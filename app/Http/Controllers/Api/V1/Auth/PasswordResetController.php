<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\Auth\ResetPasswordRequest;
use App\Modules\Identity\Models\User;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Réinitialisation de mot de passe par jeton envoyé par email.
 */
class PasswordResetController extends Controller
{
    /**
     * Demander un lien de réinitialisation.
     *
     * Endpoint public. Aucune donnée sensible n'est renvoyée ni journalisée.
     *
     * @response array{data: array{message: string}, meta: array{}}
     * @response 422 array{message: string, errors: array{email: array<int, string>}}
     */
    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        $status = Password::sendResetLink($request->validated());

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages(['email' => [__($status)]]);
        }

        return ApiResponse::ok(['message' => __($status)]);
    }

    /**
     * Réinitialiser le mot de passe avec le jeton reçu.
     *
     * Endpoint public. Le nouveau mot de passe doit respecter la politique
     * Laravel par défaut et être confirmé via `password_confirmation`.
     *
     * @response array{data: array{message: string}, meta: array{}}
     * @response 422 array{message: string, errors: array{email: array<int, string>}}
     */
    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password', 'password_confirmation', 'token');

        $status = Password::reset($credentials, static function (User $user, string $password): void {
            $user->forceFill(['password' => Hash::make($password)])->setRememberToken(Str::random(60));
            $user->save();
            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => [__($status)]]);
        }

        return ApiResponse::ok(['message' => __($status)]);
    }
}
