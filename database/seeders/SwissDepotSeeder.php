<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Addresses\Models\Address;
use App\Modules\Addresses\Models\EntityAddress;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\Depot;
use App\Modules\Organizations\Models\Organization;
use Illuminate\Database\Seeder;

/**
 * Agence et dépôt suisses, avec l'adresse du dépôt.
 *
 * **L'adresse compte autant que le dépôt.** La planification cherche le point de
 * départ dans `entity_addresses` : un dépôt sans liaison d'adresse ne remonte
 * pas en tête de tournée, et le chargement se retrouve au milieu du parcours.
 * C'est précisément ce que le propriétaire du projet avait dû corriger à la main
 * le 26 août 2026.
 *
 * L'agence est créée parce qu'un dépôt lui appartient : le schéma ne connaît pas
 * de dépôt flottant. Meyrin, à côté de l'aéroport de Genève, est un lieu
 * plausible pour un entrepôt.
 */
class SwissDepotSeeder extends Seeder
{
    public const string AGENCY_CODE = 'CH-GE';

    public const string DEPOT_CODE = 'CH-GE-MEYRIN';

    /** Coordonnées réelles de Meyrin, zone industrielle. */
    private const array DEPOT_ADDRESS = [
        'name' => 'Dépôt Genève-Meyrin',
        'address_number' => '123',
        'route' => 'Route de Meyrin',
        'address_line_1' => '123 Route de Meyrin',
        'postal_code' => '1217',
        'city' => 'Meyrin',
        'country' => 'CH',
        'latitude' => 46.23330000,
        'longitude' => 6.08330000,
        'status' => 'active',
    ];

    public function run(): void
    {
        // `local` seulement, et non `testing` : neuf cents commandes par
        // organisation alourdiraient chaque test d'une demi-minute et
        // fausseraient toute assertion qui compte, pagine ou cherche.
        if (! app()->environment('local')) {
            return;
        }

        foreach (Organization::cursor() as $organization) {
            $this->declare($organization);
        }
    }

    private function declare(Organization $organization): void
    {
        $agency = Agency::firstOrCreate(
            ['organization_id' => $organization->id, 'code' => self::AGENCY_CODE],
            [
                'name' => 'Agence Genève',
                'short_name' => 'Genève',
                'status' => 'active',
            ],
        );

        $depot = Depot::firstOrCreate(
            ['agency_id' => $agency->id, 'code' => self::DEPOT_CODE],
            ['name' => self::DEPOT_ADDRESS['name'], 'status' => 'active'],
        );

        $link = EntityAddress::where('organization_id', $organization->id)
            ->where('entity_type', 'depot')
            ->where('entity_id', $depot->id)
            ->first();

        if ($link !== null) {
            return;
        }

        $address = Address::create(self::DEPOT_ADDRESS);

        EntityAddress::create([
            'organization_id' => $organization->id,
            'address_id' => $address->id,
            'entity_type' => 'depot',
            'entity_id' => $depot->id,
            'address_type' => 'operational',
            'is_default' => true,
        ]);
    }
}
