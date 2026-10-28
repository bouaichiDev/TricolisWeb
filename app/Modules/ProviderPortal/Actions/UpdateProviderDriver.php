<?php

declare(strict_types=1);

namespace App\Modules\ProviderPortal\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Drivers\Actions\UpdateDriverAction;
use App\Modules\Drivers\DTOs\UpdateDriverData;
use App\Modules\Drivers\Models\Driver;
use App\Modules\Identity\Models\User;
use App\Modules\ProviderPortal\Services\DriverAccountOwnership;
use App\Modules\ProviderPortal\Services\ProviderResourceScope;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Modifie un chauffeur du fournisseur courant, relu sous verrou dans son
 * périmètre.
 *
 * L'identité (prénom, nom) et le téléphone vivent sur le compte du chauffeur.
 * Le fournisseur ne les change que si le compte lui est propre
 * (`DriverAccountOwnership`), et cette modification est auditée. Sinon une
 * valeur **inchangée** est acceptée — l'interface renvoie tout le formulaire —
 * mais une valeur différente est refusée sur son champ.
 *
 * @phpstan-type Input array{code?: string, firstName?: string, lastName?: string, phone?: string|null}
 */
final readonly class UpdateProviderDriver
{
    private const array ACCOUNT_FIELDS = ['firstName' => 'first_name', 'lastName' => 'last_name', 'phone' => 'phone'];

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

            if ($user === null) {
                // Chauffeur antérieur aux comptes : le nom se recompose de la saisie.
                if (isset($input['firstName']) || isset($input['lastName'])) {
                    $attributes['name'] = trim(($input['firstName'] ?? '').' '.($input['lastName'] ?? '')) ?: $driver->name;
                }
            } elseif (DriverAccountOwnership::owns($driver)) {
                $this->updateAccount($driver, $user, $input, $context);
                $attributes['name'] = trim($user->first_name.' '.$user->last_name);
            } else {
                $this->refuseAccountChanges($user, $input);
            }

            return $this->update->execute($driver, UpdateDriverData::fromValidated($attributes), $context);
        });
    }

    /**
     * @param  Input  $input
     */
    private function refuseAccountChanges(User $user, array $input): void
    {
        foreach (self::ACCOUNT_FIELDS as $field => $column) {
            if (array_key_exists($field, $input) && (string) $input[$field] !== (string) $user->{$column}) {
                throw ValidationException::withMessages([$field => ['Ce compte sert aussi ailleurs : cette information ne se modifie pas ici.']]);
            }
        }
    }

    /**
     * @param  Input  $input
     */
    private function updateAccount(Driver $driver, User $user, array $input, AuditContext $context): void
    {
        $before = $user->only(array_values(self::ACCOUNT_FIELDS));
        $user->forceFill([
            'first_name' => $input['firstName'] ?? $user->first_name,
            'last_name' => $input['lastName'] ?? $user->last_name,
            'phone' => array_key_exists('phone', $input) ? $input['phone'] : $user->phone,
        ])->save();
        $after = $user->only(array_values(self::ACCOUNT_FIELDS));

        if ($before !== $after) {
            $this->audit->execute($context->organizationId, $context->user, 'driver.account_updated', $driver, $before, $after, null, $context->ipAddress);
        }
    }
}
