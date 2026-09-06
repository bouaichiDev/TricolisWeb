<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\Auth\ResetPasswordRequest;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\SelfServicePasswordReset;
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
     * La réponse rendue quoi qu'il arrive, sauf débit excessif.
     *
     * Elle ne dit pas si l'adresse est connue : c'est tout l'objet du choix
     * ci-dessous.
     */
    private const string NEUTRAL_MESSAGE = 'Si un compte administrateur existe pour cette adresse, un lien de réinitialisation vient de lui être envoyé. Les autres comptes demandent la réinitialisation à leur administrateur.';

    /**
     * Demander un lien de réinitialisation.
     *
     * Endpoint public. Aucune donnée sensible n'est renvoyée ni journalisée.
     *
     * **Le lien ne part qu'aux administrateurs.** Les autres comptes demandent
     * la réinitialisation à leur administrateur, qui la leur rend depuis leur
     * fiche — `MemberPasswordController` porte ce chemin depuis toujours.
     * `SelfServicePasswordReset` dit qui est administrateur, et ce n'est jamais
     * un nom de rôle.
     *
     * **La réponse est la même dans tous les cas.** Répondre « nous ne
     * connaissons pas cette adresse » transformait ce formulaire en annuaire :
     * essayées une par une, les adresses d'une société révélaient lesquelles
     * ont un compte chez nous — sans authentification, et sans que personne
     * s'en aperçoive. Distinguer l'administrateur du simple membre en dirait
     * tout autant : cela désignerait, dans une liste d'adresses, celles qui
     * ouvrent le plus de portes. Le seul écart conservé est le débit excessif :
     * là, taire la raison ferait croire à un envoi qui n'a pas eu lieu, et
     * l'utilisateur réessaierait indéfiniment.
     *
     * @response array{data: array{message: string}, meta: array{}}
     * @response 422 array{message: string, errors: array{email: array<int, string>}}
     */
    public function forgot(ForgotPasswordRequest $request, SelfServicePasswordReset $selfService): JsonResponse
    {
        $credentials = $request->validated();
        $user = User::where('email', Str::lower((string) $credentials['email']))->first();

        if ($user !== null && $selfService->isAllowedFor($user)) {
            $status = Password::sendResetLink($credentials);

            if ($status === Password::RESET_THROTTLED) {
                throw ValidationException::withMessages(['email' => [__($status)]]);
            }
        }

        return ApiResponse::ok(['message' => self::NEUTRAL_MESSAGE]);
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
