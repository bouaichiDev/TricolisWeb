<?php

declare(strict_types=1);

namespace App\Modules\ProviderPortal\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\CustomerPortal\Models\CustomerUser;
use App\Modules\Drivers\Actions\UpdateDriverAction;
use App\Modules\Drivers\DTOs\UpdateDriverData;
use App\Modules\Drivers\Models\Driver;
use App\Modules\Identity\Models\User;
use App\Modules\Organizations\Models\OrganizationUser;
use App\Modules\ProviderPortal\Models\ProviderUser;
use App\Modules\ProviderPortal\Services\ProviderResourceScope;
use App\Shared\Enums\UserStatus;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Modifie un chauffeur du fournisseur courant, relu sous verrou dans son
 * périmètre.
 *
 * Les comptes sont **globaux** : un même `User` peut servir ailleurs (autre
 * transporteur, portail client ou fournisseur). Le fournisseur ne modifie donc
 * le compte que s'il est propre à ce chauffeur — encore invité, une seule
 * appartenance, aucun accès portail, un seul chauffeur — et cette modification
 * est auditée. Sinon seul le nom du chauffeur change, et le téléphone du
 * compte est refusé.
 *
 * @phpstan-type Input array{code?: string, firstName?: string, lastName?: string, phone?: string|null}
 */
final readonly class UpdateProviderDriver
{
    public function __construct(
        private ProviderResourceScope $scope,
        private UpdateDriverAction $update,
        private WriteAuditLog $audit,
    ) {}

    /**
     * @param  Input  $input
     */
    public function execute(string $driverId, array $input, AuditContext $context): Driver
    {
        return DB::transaction(function () use ($driverId, $input, $context): Driver {
            $driver = $this->scope->driver($driverId, lock: true)->load('user');
            $user = $driver->user;
            $attributes = array_intersect_key($input, ['code' => true]);
            $identity = isset($input['firstName']) || isset($input['lastName']);

            if ($user !== null && $this->owns($driver, $user)) {
                $this->updateAccount($driver, $user, $input, $context);
                $attributes['name'] = trim($user->first_name.' '.$user->last_name);
            } else {
                if (array_key_exists('phone', $input)) {
                    throw ValidationException::withMessages(['phone' => ['Le téléphone de ce chauffeur ne peut pas être modifié ici.']]);
                }

                if ($identity) {
                    $attributes['name'] = trim(($input['firstName'] ?? $user?->first_name ?? '').' '.($input['lastName'] ?? $user?->last_name ?? '')) ?: $driver->name;
                }
            }

            return $this->update->execute($driver, UpdateDriverData::fromValidated($attributes), $context);
        });
    }

    private function owns(Driver $driver, User $user): bool
    {
        return $user->status === UserStatus::INVITED
            && OrganizationUser::where('user_id', $user->id)->count() <= 1
            && ! ProviderUser::where('user_id', $user->id)->exists()
            && ! CustomerUser::where('user_id', $user->id)->exists()
            && Driver::where('user_id', $user->id)->whereKeyNot($driver->id)->doesntExist();
    }

    /**
     * @param  Input  $input
     */
    private function updateAccount(Driver $driver, User $user, array $input, AuditContext $context): void
    {
        $before = $user->only(['first_name', 'last_name', 'phone']);
        $user->forceFill([
            'first_name' => $input['firstName'] ?? $user->first_name,
            'last_name' => $input['lastName'] ?? $user->last_name,
            'phone' => array_key_exists('phone', $input) ? $input['phone'] : $user->phone,
        ])->save();
        $after = $user->only(['first_name', 'last_name', 'phone']);

        if ($before !== $after) {
            $this->audit->execute($context->organizationId, $context->user, 'driver.account_updated', $driver, $before, $after, null, $context->ipAddress);
        }
    }
}
