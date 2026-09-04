<?php

declare(strict_types=1);

namespace App\Modules\Communications\Listeners;

use App\Modules\Communications\Actions\ApplyCommunicationRules;
use App\Modules\Communications\Enums\CommunicationEventType;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Events\OrderCreated;
use App\Modules\Orders\Events\OrderStatusChanged;

/**
 * Traduit un fait de commande en événement de communication.
 *
 * La correspondance vit ici, et nulle part ailleurs : les Orders ne connaissent
 * pas `CommunicationEventType`, et n'ont pas à le connaître. Une commande
 * change d'état ; ce que la messagerie en fait la regarde seule.
 *
 * **Trois transitions seulement sont traduites**, et c'est délibéré. Les huit
 * autres valeurs de `CommunicationEventType` — rendez-vous, chauffeur, arrêt
 * imminent, POD, réclamation — n'ont pas de fait qui les émette aujourd'hui, et
 * le §2 interdit d'inventer leur sémantique : à partir de quand un arrêt est-il
 * « imminent » ? Les câbler au jugé produirait des messages au mauvais moment,
 * ce qui est pire que pas de message.
 *
 * Les trois retenues ne laissent place à aucune interprétation : la commande
 * est créée, confirmée, ou annulée.
 */
final readonly class CreateCommunicationsForOrderEvents
{
    public function __construct(private ApplyCommunicationRules $rules) {}

    public function handleCreated(OrderCreated $event): void
    {
        $this->rules->execute($event->order, CommunicationEventType::ORDER_CREATED, $event->context);
    }

    public function handleStatusChanged(OrderStatusChanged $event): void
    {
        $type = match ($event->to) {
            OrderStatus::CONFIRMED => CommunicationEventType::ORDER_CONFIRMED,
            OrderStatus::CANCELLED => CommunicationEventType::ORDER_CANCELLED,
            default => null,
        };

        if ($type === null) {
            return;
        }

        $this->rules->execute($event->order, $type, $event->context);
    }
}
