<?php

declare(strict_types=1);

namespace App\Modules\ProviderPortal\Actions;

use App\Modules\ProviderPortal\Models\ProviderUser;
use App\Modules\ProviderPortal\Services\ProviderPortalAudit;
use App\Modules\ProviderPortal\Support\ProviderPortalToken;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Change le mot de passe du compte connecté au portail fournisseur.
 *
 * L'ancien mot de passe est exigé : un poste resté ouvert ne suffit pas à
 * prendre le compte. Les **autres** sessions du portail fournisseur sont
 * fermées — qui change son mot de passe parce qu'on le lui a pris doit en
 * chasser l'intrus —, la session courante est gardée.
 */
final readonly class ChangeProviderPassword
{
    public function __construct(private ProviderPortalAudit $audit) {}

    public function execute(ProviderUser $membership, PersonalAccessToken $current, string $currentPassword, string $password, ?string $ipAddress = null): void
    {
        $user = $membership->user;

        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages(['currentPassword' => ['Le mot de passe actuel est incorrect.']]);
        }

        $user->forceFill(['password' => $password])->save();
        $user->tokens()
            ->where('name', ProviderPortalToken::NAME)
            ->whereKeyNot($current->getKey())
            ->delete();

        $this->audit->record($membership->provider, $user, 'password_changed', $membership, null, [], $ipAddress);
    }
}
