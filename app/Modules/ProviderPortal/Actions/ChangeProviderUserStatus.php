<?php

declare(strict_types=1);

namespace App\Modules\ProviderPortal\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\ProviderPortal\Enums\ProviderInvitationStatus;
use App\Modules\ProviderPortal\Models\ProviderUser;
use App\Modules\ProviderPortal\Models\ProviderUserInvitation;
use App\Modules\ProviderPortal\Services\ProviderPortalAudit;
use App\Modules\ProviderPortal\Support\ProviderPortalToken;
use App\Shared\Enums\UserStatus;
use Illuminate\Validation\ValidationException;

/**
 * Désactive ou réactive l'accès d'un compte au portail d'un fournisseur.
 *
 * Seul le **rattachement** change : le `User` peut servir ailleurs. Désactiver
 * révoque aussi les jetons ouverts sur ce fournisseur — le middleware les
 * refuserait déjà, mais un jeton inutilisable n'a pas à survivre.
 */
final readonly class ChangeProviderUserStatus
{
    public function __construct(private ProviderPortalAudit $audit) {}

    public function disable(ProviderUser $target, ?User $actor, ?string $ipAddress = null): ProviderUser
    {
        if ($target->status === UserStatus::DISABLED) {
            return $target;
        }

        $old = $target->status->value;
        $target->update(['status' => UserStatus::DISABLED, 'disabled_at' => now()]);
        // Une invitation retirée ne doit plus s'activer par le lien déjà envoyé.
        ProviderUserInvitation::where('provider_user_id', $target->id)
            ->where('status', ProviderInvitationStatus::PENDING->value)
            ->update(['status' => ProviderInvitationStatus::CANCELLED->value]);
        $target->user->tokens()
            ->where('name', ProviderPortalToken::NAME)
            ->where('abilities', 'like', '%"'.ProviderPortalToken::PROVIDER_PREFIX.$target->provider_id.'"%')
            ->delete();

        $this->audit->record($target->provider, $actor, 'user_disabled', $target, ['status' => $old], ['status' => UserStatus::DISABLED->value], $ipAddress);

        return $target;
    }

    public function enable(ProviderUser $target, ?User $actor, ?string $ipAddress = null): ProviderUser
    {
        if ($target->status === UserStatus::ACTIVE) {
            return $target;
        }

        if ($target->activated_at === null) {
            throw ValidationException::withMessages(['member' => ['Ce compte n’a pas encore accepté son invitation : renvoyez-la plutôt.']]);
        }

        $old = $target->status->value;
        $target->update(['status' => UserStatus::ACTIVE, 'disabled_at' => null]);
        $this->audit->record($target->provider, $actor, 'user_enabled', $target, ['status' => $old], ['status' => UserStatus::ACTIVE->value], $ipAddress);

        return $target;
    }
}
