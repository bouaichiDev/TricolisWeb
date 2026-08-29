<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Pricing\Models\PricingVariable;
use App\Modules\Pricing\Services\PricingVariableSources;
use Illuminate\Database\Seeder;

/**
 * Le catalogue de variables livré avec l'application.
 *
 * Un référentiel de plateforme, semé comme les statuts : il doit exister avant
 * qu'un organisme n'écrive son premier barème, sinon aucune formule ne serait
 * valide.
 *
 * **Rejouable, et sans écraser.** Un superadmin qui renomme une variable ou en
 * désactive une garde son choix ; le semis ne fait qu'ajouter ce qui manque —
 * c'est la même règle que pour le menu, et pour la même raison.
 */
class PricingVariableSeeder extends Seeder
{
    /**
     * @var list<array{code: string, label: string, source: string, unit: ?string, position: int}>
     */
    private const array VARIABLES = [
        ['code' => 'poids', 'label' => 'Poids', 'source' => 'order_service.weight', 'unit' => 'kg', 'position' => 10],
        ['code' => 'volume', 'label' => 'Volume', 'source' => 'order_service.volume', 'unit' => 'm3', 'position' => 20],
        ['code' => 'quantite', 'label' => 'Quantité', 'source' => 'order_service.quantity', 'unit' => null, 'position' => 30],
        ['code' => 'nombre_colis', 'label' => 'Nombre de colis', 'source' => 'order_service.package_count', 'unit' => null, 'position' => 40],
        ['code' => 'duree', 'label' => 'Durée prévue', 'source' => 'order_service.required_time_minutes', 'unit' => 'min', 'position' => 50],
        ['code' => 'distance', 'label' => 'Distance depuis le dépôt', 'source' => 'depot.distance_km', 'unit' => 'km', 'position' => 60],

        ['code' => 'code_postal', 'label' => 'Code postal', 'source' => 'address.postal_code', 'unit' => null, 'position' => 70],
        ['code' => 'ville', 'label' => 'Ville', 'source' => 'address.city', 'unit' => null, 'position' => 80],
        ['code' => 'pays', 'label' => 'Pays', 'source' => 'address.country', 'unit' => null, 'position' => 90],
        ['code' => 'service', 'label' => 'Code de prestation', 'source' => 'service.code', 'unit' => null, 'position' => 100],
    ];

    public function run(): void
    {
        $sources = PricingVariableSources::all();

        foreach (self::VARIABLES as $variable) {
            PricingVariable::firstOrCreate(
                ['code' => $variable['code']],
                [
                    'label' => $variable['label'],
                    'description' => $sources[$variable['source']]['label'] ?? null,
                    'kind' => $sources[$variable['source']]['kind'] ?? PricingVariableSources::NUMERIC,
                    'source_key' => $variable['source'],
                    'unit' => $variable['unit'],
                    'position' => $variable['position'],
                    'is_active' => true,
                ],
            );
        }
    }
}
