<?php

declare(strict_types=1);

namespace App\Modules\Statuses\Services;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Orders\Actions\ChangeOrderStatus;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pose le statut qu'une règle de propagation entraîne.
 *
 * **Une commande passe par son Action, jamais par une écriture directe.**
 * `ChangeOrderStatus` vérifie la transition et sort la marchandise du stock à la
 * confirmation ; écrire la colonne à la place laisserait un dépôt qui ment, et
 * un cycle de vie que l'administrateur croyait gouverner.
 *
 * Les autres entités n'ont pas d'Action de ce genre : la colonne est écrite ici,
 * et le journal d'audit garde la trace — avec **d'où vient** le changement, sans
 * quoi une commande passerait toute seule d'un statut à l'autre sans que rien
 * n'explique pourquoi.
 *
 * **Aucun refus n'arrête l'opération en cours.** La propagation est un
 * automatisme confortable, pas une règle métier : si la transition n'est pas
 * déclarée, ou si le statut cible exige un motif qu'une machine ne peut pas
 * donner, la règle est simplement notée au journal. Faire échouer le chargement
 * d'un colis parce qu'une règle de confort est mal réglée serait pire que de ne
 * rien propager.
 */
final readonly class ApplyPropagatedStatus
{
    public function __construct(
        private WriteAuditLog $audit,
        private ChangeOrderStatus $orders,
    ) {}

    public function execute(Model $entity, string $code, Model $from, string $fromCode): void
    {
        if (StatusPropagator::codeOf($entity) === $code) {
            return;
        }

        $origin = $from->getMorphClass().' « '.$fromCode.' »';

        try {
            $entity instanceof Order
                ? $this->order($entity, $code, $origin)
                : $this->write($entity, $code, $origin);
        } catch (Throwable $exception) {
            Log::warning('Propagation de statut refusée', [
                'entity' => $entity->getMorphClass(),
                'id' => $entity->getKey(),
                'target' => $code,
                'origin' => $origin,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function order(Order $order, string $code, string $origin): void
    {
        $target = OrderStatus::tryFrom($code);

        if ($target === null) {
            Log::warning('Propagation vers un statut de commande inconnu', ['target' => $code]);

            return;
        }

        // `manual: false` : la transition doit exister, mais elle n'a pas à être
        // posable à la main — c'est une règle, pas un clic.
        $this->orders->execute(
            order: $order,
            target: $target,
            user: null,
            reasonCode: 'status_propagation',
            reasonText: 'Propagé depuis '.$origin,
            manual: false,
        );
    }

    private function write(Model $entity, string $code, string $origin): void
    {
        $old = ['status' => StatusPropagator::codeOf($entity)];

        $entity->forceFill(['status' => $code])->save();

        $organizationId = $this->organizationOf($entity);

        if ($organizationId === null) {
            return;
        }

        $this->audit->execute(
            $organizationId,
            null,
            'status_changed',
            $entity,
            $old,
            ['status' => $code, 'propagated_from' => $origin],
        );
    }

    /**
     * L'organisation de l'entité, ou celle de sa commande : ni les colis ni les
     * lignes ne portent la colonne, et le journal est indexé par organisation.
     */
    private function organizationOf(Model $entity): ?string
    {
        $own = $entity->getAttribute('organization_id');

        if (is_string($own)) {
            return $own;
        }

        $order = $entity->getAttribute('order_id');

        return $order === null ? null : Order::whereKey($order)->value('organization_id');
    }
}
