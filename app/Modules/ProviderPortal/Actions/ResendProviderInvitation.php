<?php

declare(strict_types=1);

namespace App\Modules\ProviderPortal\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\ProviderPortal\Models\ProviderUser;
use App\Modules\ProviderPortal\Services\IssueProviderInvitation;
use App\Modules\ProviderPortal\Services\ProviderPortalAudit;
use App\Shared\Enums\UserStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Renvoie une invitation : nouveau lien, l'ancien cesse de valoir.
 *
 * Seulement pour un compte encore `invited` : un compte actif n'a pas besoin
 * de lien d'activation.
 */
final readonly class ResendProviderInvitation
{
    public function __construct(
        private IssueProviderInvitation $invitations,
        private ProviderPortalAudit $audit,
    ) {}

    public function execute(ProviderUser $target, ?User $actor, ?string $ipAddress = null): ProviderUser
    {
        if ($target->status !== UserStatus::INVITED) {
            throw ValidationException::withMessages(['member' => ['Ce compte a déjà activé son accès.']]);
        }

        DB::transaction(function () use ($target, $actor): void {
            $this->invitations->execute($target, $target->roles()->value('roles.id'), $actor);
            $target->update(['invited_at' => now(), 'invited_by' => $actor?->id]);
        });

        $this->audit->record($target->provider, $actor, 'invitation_resent', $target, null, ['email' => $target->user->email], $ipAddress);

        return $target;
    }
}
