<?php

declare(strict_types=1);

namespace App\Modules\ProviderPortal\Actions;

use App\Modules\Drivers\Actions\UpdateDriverAction;
use App\Modules\Drivers\DTOs\UpdateDriverData;
use App\Modules\Drivers\Models\Driver;
use App\Modules\ProviderPortal\Services\ProviderResourceScope;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Modifie un chauffeur du fournisseur courant, relu sous verrou dans son
 * périmètre.
 *
 * L'identité et le téléphone vivent sur le compte du chauffeur ; le nom du
 * chauffeur en est recomposé, comme à la création. Le reste passe par
 * `UpdateDriverAction`, qui audite.
 *
 * @phpstan-type Input array{code?: string, firstName?: string, lastName?: string, phone?: string|null}
 */
final readonly class UpdateProviderDriver
{
    public function __construct(
        private ProviderResourceScope $scope,
        private UpdateDriverAction $update,
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

            if ($user !== null && array_intersect_key($input, ['firstName' => true, 'lastName' => true, 'phone' => true]) !== []) {
                $user->forceFill([
                    'first_name' => $input['firstName'] ?? $user->first_name,
                    'last_name' => $input['lastName'] ?? $user->last_name,
                    'phone' => array_key_exists('phone', $input) ? $input['phone'] : $user->phone,
                ])->save();
                $attributes['name'] = trim($user->first_name.' '.$user->last_name);
            } elseif ($user === null && (isset($input['firstName']) || isset($input['lastName']))) {
                // Chauffeur antérieur aux comptes : le nom se recompose de la saisie.
                $attributes['name'] = trim(($input['firstName'] ?? '').' '.($input['lastName'] ?? '')) ?: $driver->name;
            }

            return $this->update->execute($driver, UpdateDriverData::fromValidated($attributes), $context);
        });
    }
}
