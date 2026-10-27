<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Actions;

use App\Modules\CustomerPortal\Models\CustomerUser;
use App\Modules\CustomerPortal\Services\CustomerPortalAudit;
use App\Modules\CustomerPortal\Services\ManagedMemberGuard;
use App\Modules\CustomerPortal\Support\CustomerPortalToken;
use App\Shared\Enums\UserStatus;
use Illuminate\Validation\ValidationException;

/**
 * Désactive ou réactive un membre du portail.
 *
 * Seule l'**appartenance** change : le `User` peut servir ailleurs (chez un
 * autre client, chez un transporteur) et ne doit pas en pâtir.
 *
 * Désactiver révoque aussi les jetons de portail ouverts sur ce client : le
 * middleware refuserait déjà chaque requête, mais un jeton inutilisable n'a
 * pas à survivre dans la table.
 */
final readonly class ChangeCustomerUserStatus
{
    public function __construct(private CustomerPortalAudit $audit) {}

    public function disable(CustomerUser $actor, CustomerUser $target, ?string $ipAddress = null): CustomerUser
    {
        ManagedMemberGuard::ensureManageable($actor, $target);

        if ($target->status === UserStatus::DISABLED) {
            return $target;
        }

        $old = $target->status->value;
        $target->update(['status' => UserStatus::DISABLED, 'disabled_at' => now()]);

        $target->user->tokens()
            ->where('name', CustomerPortalToken::NAME)
            ->where('abilities', 'like', '%'.CustomerPortalToken::CUSTOMER_PREFIX.$target->customer_id.'%')
            ->delete();

        $this->audit->record($target->customer, $actor->user, 'user_disabled', $target, ['status' => $old], ['status' => UserStatus::DISABLED->value], $ipAddress);

        return $target;
    }

    public function enable(CustomerUser $actor, CustomerUser $target, ?string $ipAddress = null): CustomerUser
    {
        ManagedMemberGuard::ensureManageable($actor, $target);

        if ($target->status === UserStatus::ACTIVE) {
            return $target;
        }

        if ($target->activated_at === null) {
            throw ValidationException::withMessages(['member' => ['Ce membre n’a pas encore accepté son invitation : renvoyez-la plutôt.']]);
        }

        $old = $target->status->value;
        $target->update(['status' => UserStatus::ACTIVE, 'disabled_at' => null]);

        $this->audit->record($target->customer, $actor->user, 'user_enabled', $target, ['status' => $old], ['status' => UserStatus::ACTIVE->value], $ipAddress);

        return $target;
    }
}
