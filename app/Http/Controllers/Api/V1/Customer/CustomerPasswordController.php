<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\Auth\ResetPasswordRequest;
use App\Modules\CustomerPortal\Actions\SendCustomerPasswordReset;
use App\Modules\CustomerPortal\Models\CustomerUser;
use App\Modules\CustomerPortal\Services\CustomerPortalAudit;
use App\Modules\Identity\Models\User;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Mot de passe oublié, côté portail client.
 */
class CustomerPasswordController extends Controller
{
    private const string NEUTRAL_MESSAGE = 'Si un accès au portail existe pour cette adresse, un lien de réinitialisation vient d’y être envoyé.';

    /**
     * Demander un lien. La réponse est la même, que l'adresse soit connue ou non.
     */
    public function forgot(ForgotPasswordRequest $request, SendCustomerPasswordReset $send): JsonResponse
    {
        $send->execute((string) $request->validated('email'));

        return ApiResponse::ok(['message' => self::NEUTRAL_MESSAGE]);
    }

    /**
     * Choisir un nouveau mot de passe avec le jeton reçu.
     *
     * Les jetons de portail ouverts sont révoqués : qui réinitialise parce
     * qu'on lui a pris son compte doit en chasser l'intrus.
     */
    public function reset(ResetPasswordRequest $request, CustomerPortalAudit $audit): JsonResponse
    {
        $credentials = $request->only('email', 'password', 'password_confirmation', 'token');
        $resetUser = null;

        $status = Password::reset($credentials, static function (User $user, string $password) use (&$resetUser): void {
            $user->forceFill(['password' => $password])->setRememberToken(Str::random(60));
            $user->save();
            $user->tokens()->where('name', 'customer-portal')->delete();
            $resetUser = $user;
            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => [__($status)]]);
        }

        $membership = CustomerUser::with('customer')->where('user_id', $resetUser->id)->first();

        if ($membership !== null) {
            $audit->record($membership->customer, $resetUser, 'password_reset', $membership, null, [], $request->ip());
        }

        return ApiResponse::ok(['message' => __($status)]);
    }
}
