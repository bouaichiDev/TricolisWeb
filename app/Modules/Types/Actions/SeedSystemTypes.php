<?php

declare(strict_types=1);

namespace App\Modules\Types\Actions;

use App\Modules\Types\Models\Type;

/**
 * Dote une organisation de ses sources structurelles.
 *
 * `vehicles.vehicle_type_id`, `packages.package_type_id` et
 * `packages.grouping_type_id` désignent des valeurs de ces trois sources : une
 * organisation qui en serait dépourvue ne pourrait ni créer un véhicule ni
 * classer un colis, et l'écran des référentiels s'ouvrirait sur rien.
 *
 * Appelée à chaque création d'organisation, aux côtés de
 * `SyncOrganizationMenu`. `firstOrCreate` la rend rejouable sans dommage.
 */
final readonly class SeedSystemTypes
{
    /** Code de la source => libellé par défaut. */
    private const array SOURCES = [
        'vehicle' => 'Type de véhicule',
        'package' => 'Type de colis',
        'grouping' => 'Type de groupage',
    ];

    public function execute(string $organizationId): void
    {
        foreach (self::SOURCES as $code => $name) {
            Type::firstOrCreate(
                ['organization_id' => $organizationId, 'code' => $code],
                ['name' => $name, 'status' => 'active', 'is_system' => true],
            );
        }
    }
}
