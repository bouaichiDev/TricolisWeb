<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Communications\Enums\CommunicationStatus;
use App\Modules\Customers\Enums\CustomerStatus;
use App\Modules\Orders\Enums\OrderServiceStatus;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Organizations\Enums\SubscriptionStatus;
use App\Modules\Statuses\Models\Status;
use App\Modules\Tours\Enums\TourStatus;
use App\Modules\Tours\Enums\TourStopStatus;
use App\Shared\Database\MorphMap;
use App\Shared\Enums\OrganizationStatus;
use App\Shared\Enums\UserStatus;
use Illuminate\Database\Seeder;

/**
 * Remplit le référentiel à partir des énumérations qui existent déjà.
 *
 * **Rien n'est inventé.** Seules les entités dont le statut est gouverné par une
 * énumération PHP sont semées : ce sont les seules dont on connaisse la liste
 * exacte. Les autres colonnes `status` sont des chaînes libres, et deviner leurs
 * valeurs produirait un référentiel faux dès la première commande.
 *
 * **Une ligne existante n'est jamais réécrite.** Libellé, icône, rang et
 * comportement sont réglables depuis l'écran ; rejouer le seeder ne doit pas
 * effacer ce qu'un administrateur a décidé. Seules les lignes manquantes sont
 * créées.
 */
class StatusSeeder extends Seeder
{
    /**
     * Énumérations connues, par entité.
     *
     * @var array<string, class-string>
     */
    private array $enums = [
        MorphMap::ORDER => OrderStatus::class,
        MorphMap::ORDER_SERVICE => OrderServiceStatus::class,
        MorphMap::ORDER_COMMUNICATION => CommunicationStatus::class,
        MorphMap::CUSTOMER => CustomerStatus::class,
        MorphMap::USER => UserStatus::class,
        MorphMap::ORGANIZATION => OrganizationStatus::class,
        MorphMap::SUBSCRIPTION => SubscriptionStatus::class,
        MorphMap::TOUR => TourStatus::class,
        MorphMap::TOUR_STOP => TourStopStatus::class,
    ];

    public function run(): void
    {
        foreach ($this->enums as $source => $enum) {
            $this->seedSource((string) $source, $enum);
        }
    }

    /**
     * @param  class-string  $enum
     */
    private function seedSource(string $source, string $enum): void
    {
        if (! enum_exists($enum)) {
            return;
        }

        $rank = 0;

        foreach ($enum::cases() as $case) {
            $rank++;

            $status = Status::firstOrNew(['source' => $source, 'code' => $case->value]);

            if ($status->exists) {
                continue;
            }

            $status->fill([
                'status' => $rank,
                'label' => method_exists($case, 'label') ? $case->label() : $case->name,
                'position' => $rank * 10,
                // Les deux comportements viennent de l'énumération quand elle
                // les définit ; ailleurs ils restent au défaut de la colonne.
                'allows_content_changes' => method_exists($case, 'allowsContentChanges')
                    && $case->allowsContentChanges(),
                'requires_reason' => method_exists($case, 'requiresReason') && $case->requiresReason(),
            ])->save();
        }
    }
}
