<?php

declare(strict_types=1);

namespace App\Modules\Orders\Events;

use App\Modules\Orders\Models\Order;
use App\Shared\Support\AuditContext;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Une commande vient d'être créée.
 *
 * Émis **après le commit**, jamais pendant : un abonné qui agirait dans la
 * transaction pourrait écrire à propos d'une commande que le rollback fait
 * ensuite disparaître.
 *
 * L'événement transporte le contexte d'audit — qui a agi, depuis où — parce que
 * ce qu'il déclenche s'audite aussi, et reconstruire ce contexte plus loin
 * obligerait à retrouver une requête HTTP que les abonnés n'ont pas.
 */
final readonly class OrderCreated
{
    use Dispatchable;

    public function __construct(
        public Order $order,
        public AuditContext $context,
    ) {}
}
