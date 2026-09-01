<?php

declare(strict_types=1);

namespace App\Modules\Orders\Events;

use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Shared\Support\AuditContext;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Une commande a changé d'état.
 *
 * Un seul événement pour toutes les transitions, portant l'état de départ et
 * celui d'arrivée. Une classe par statut aurait multiplié dix fichiers
 * identiques, et obligé chaque nouvel état du référentiel à en créer un
 * onzième.
 *
 * `from` peut être nul : une commande sans statut initial en prend un.
 *
 * Émis **après le commit** — voir `OrderCreated`.
 */
final readonly class OrderStatusChanged
{
    use Dispatchable;

    public function __construct(
        public Order $order,
        public ?OrderStatus $from,
        public OrderStatus $to,
        public AuditContext $context,
    ) {}
}
