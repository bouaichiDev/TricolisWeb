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
 * L'administrateur plateforme complète le reste depuis l'écran — c'est
 * précisément ce que ce référentiel lui donne.
 *
 * Rejouable : une ligne existante est mise à jour, jamais dupliquée, et
 * `active` comme `is_to_send` ne sont pas réécrits — ce sont des réglages.
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

        // Les communications n'ont pas d'alias morphique : leur statut vit sur
        // `order_communications`, dont l'alias est déclaré.
        $this->seedSource(MorphMap::ORDER_COMMUNICATION, CommunicationStatus::class);
    }

    /**
     * @param  class-string  $enum
     */
    private function seedSource(string $source, string $enum): void
    {
        if (! enum_exists($enum)) {
            return;
        }

        $position = 0;

        foreach ($enum::cases() as $case) {
            $position += 10;

            Status::updateOrCreate(
                ['source' => $source, 'code' => $case->value],
                [
                    'status' => $position / 10,
                    'label' => method_exists($case, 'label') ? $case->label() : $case->name,
                    'position' => $position,
                ],
            );
        }
    }
}
