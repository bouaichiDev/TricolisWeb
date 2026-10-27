<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Actions;

use App\Modules\CustomerPortal\Models\CustomerUser;
use App\Modules\CustomerPortal\Services\CustomerPortalAudit;
use App\Modules\CustomerPortal\Services\IssueCustomerInvitation;
use App\Modules\Identity\Models\User;
use App\Shared\Enums\UserStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Renvoie une invitation : nouveau lien, l'ancien cesse de valoir.
 *
 * Seulement pour un membre encore `invited` : un compte déjà actif n'a pas
 * besoin de lien d'activation, et en recevoir un l'inviterait à cliquer sur
 * des courriels qu'il n'attend pas.
 */
final readonly class ResendCustomerInvitation
{
    public function __construct(
        private IssueCustomerInvitation $invitations,
        private CustomerPortalAudit $audit,
    ) {}

    public function execute(CustomerUser $target, ?User $actor, ?string $ipAddress = null): CustomerUser
    {
        if ($target->status !== UserStatus::INVITED) {
            throw ValidationException::withMessages(['member' => ['Ce membre a déjà activé son compte.']]);
        }

        DB::transaction(function () use ($target, $actor): void {
            $roleId = $target->roles()->value('roles.id');
            $this->invitations->execute($target, $roleId, $actor);
            $target->update(['invited_at' => now(), 'invited_by' => $actor?->id]);
        });

        $this->audit->record($target->customer, $actor, 'invitation_resent', $target, null, ['email' => $target->user->email], $ipAddress);

        return $target;
    }
}
