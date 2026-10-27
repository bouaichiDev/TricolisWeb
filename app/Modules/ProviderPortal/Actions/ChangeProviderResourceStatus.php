<?php

declare(strict_types=1);

namespace App\Modules\ProviderPortal\Actions;

use App\Modules\Drivers\Actions\UpdateDriverAction;
use App\Modules\Drivers\DTOs\UpdateDriverData;
use App\Modules\Drivers\Models\Driver;
use App\Modules\Fleet\Actions\UpdateVehicleAction;
use App\Modules\Fleet\DTOs\UpdateVehicleData;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\ProviderPortal\Services\ProviderResourceScope;
use App\Shared\Support\AuditContext;
use Illuminate\Validation\ValidationException;

/**
 * Désactive ou réactive un chauffeur ou un véhicule du fournisseur.
 *
 * Passe par les Actions métier existantes, qui auditent le changement. Une
 * ressource affectée à une tournée planifiée, confirmée ou en cours ne se
 * désactive pas : la tournée perdrait son chauffeur ou son véhicule sans que
 * le planificateur le sache.
 */
final readonly class ChangeProviderResourceStatus
{
    public const string ACTIVE = 'active';

    public const string INACTIVE = 'inactive';

    public function __construct(
        private ProviderResourceScope $scope,
        private UpdateDriverAction $drivers,
        private UpdateVehicleAction $vehicles,
    ) {}

    public function execute(Driver|Vehicle $resource, string $status, AuditContext $context): Driver|Vehicle
    {
        if ($status === self::INACTIVE && $this->scope->isEngaged($resource)) {
            throw ValidationException::withMessages(['status' => [
                'Affecté à une tournée planifiée, confirmée ou en cours : il ne peut pas être désactivé.',
            ]]);
        }

        return $resource instanceof Driver
            ? $this->drivers->execute($resource, UpdateDriverData::fromValidated(['status' => $status]), $context)
            : $this->vehicles->execute($resource, UpdateVehicleData::fromValidated(['status' => $status]), $context);
    }
}
