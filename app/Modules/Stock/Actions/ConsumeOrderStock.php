<?php

declare(strict_types=1);

namespace App\Modules\Stock\Actions;

use App\Modules\Orders\Models\Order;
use App\Modules\Stock\DTOs\CreateStockMovementData;
use App\Modules\Stock\Services\OrderStockPlanner;
use App\Shared\Database\MorphMap;
use App\Shared\Support\AuditContext;
use Illuminate\Validation\ValidationException;

/**
 * Sort du stock ce que la commande emporte, à sa confirmation.
 *
 * Chaque ligne suivie donne **un** mouvement de sortie : source l'emplacement
 * retenu, pas de destination, entité source la ligne de commande. Le travail
 * lourd — verrou, contrôle de disponibilité, recalcul du solde, audit — reste
 * celui de `CreateStockMovementAction` : il n'est pas réécrit ici, il est
 * appelé.
 *
 * **Rien n'est prélevé à moitié.** Si un seul emplacement manque à l'appel,
 * l'ensemble est refusé et la commande reste dans son statut. C'est la
 * transaction de `ChangeOrderStatus` qui l'assure : une commande confirmée dont
 * la moitié du stock serait sortie ne se rattrape pas à la main.
 *
 * Le mouvement porte `sourceEntityId = orderLine.id`, ce qui sert de clé
 * d'idempotence : un aller-retour brouillon → confirmée ne prélève pas deux
 * fois, sans qu'aucune colonne ait à être ajoutée.
 */
final readonly class ConsumeOrderStock
{
    /** Type de mouvement des sorties produites par une confirmation. */
    public const string MOVEMENT_TYPE = 'order_confirmation';

    public function __construct(
        private OrderStockPlanner $planner,
        private CreateStockMovementAction $movements,
    ) {}

    /**
     * @param  array<string, string>  $chosenLocations  orderLineId => stockLocationId
     * @return int Nombre de lignes sorties du stock.
     */
    public function execute(Order $order, array $chosenLocations, AuditContext $context, string $now): int
    {
        $plan = $this->planner->plan($order, $chosenLocations);

        $this->assertNothingBlocks($plan);

        $moves = array_filter($plan, static fn (array $line): bool => $line['state'] === 'resolved');

        foreach ($moves as $line) {
            $this->movements->execute(
                new CreateStockMovementData(
                    stockItemId: $line['stockItemId'],
                    movementType: self::MOVEMENT_TYPE,
                    quantity: $line['quantity'],
                    sourceLocationId: $line['stockLocationId'],
                    sourceEntityType: MorphMap::ORDER_LINE,
                    sourceEntityId: $line['orderLineId'],
                ),
                $context,
                $now,
            );
        }

        return count($moves);
    }

    /**
     * Refuse la confirmation tant qu'une ligne ne sait pas d'où sortir.
     *
     * Le message nomme les lignes concernées : « choisissez un emplacement » sans
     * dire lequel des vingt articles laisse chercher.
     *
     * @param  list<array<string, mixed>>  $plan
     */
    private function assertNothingBlocks(array $plan): void
    {
        $ambiguous = $this->names($plan, 'ambiguous');
        $insufficient = $this->names($plan, 'insufficient');

        $errors = [];

        if ($ambiguous !== []) {
            $errors['stockLocations'][] = 'Ces articles sont stockés dans plusieurs emplacements : précisez lequel vider pour '.implode(', ', $ambiguous).'.';
        }

        if ($insufficient !== []) {
            $errors['stockLocations'][] = 'Le stock disponible ne couvre pas la quantité commandée pour '.implode(', ', $insufficient).'.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $plan
     * @return list<string>
     */
    private function names(array $plan, string $state): array
    {
        return array_values(array_map(
            static fn (array $line): string => (string) ($line['articleCode'] ?? $line['name']),
            array_filter($plan, static fn (array $line): bool => $line['state'] === $state),
        ));
    }
}
