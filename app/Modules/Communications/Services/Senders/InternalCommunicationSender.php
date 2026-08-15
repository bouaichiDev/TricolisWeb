<?php

declare(strict_types=1);

namespace App\Modules\Communications\Services\Senders;

use App\Modules\Communications\Models\OrderCommunication;

/**
 * Notification interne : aucun tiers n'est sollicité.
 *
 * Une notification interne **est** la ligne `order_communications` elle-même :
 * elle est consultable par l'API dès sa création. L'envoi est donc immédiat et
 * toujours réussi. Aucune table `InternalNotification` n'est créée — le §2
 * l'interdit, et elle ferait doublon.
 */
final readonly class InternalCommunicationSender implements CommunicationSender
{
    public function send(OrderCommunication $communication): SenderResult
    {
        return SenderResult::success($communication->id, [
            'channel' => 'internal_notification',
            'delivery' => 'in_app',
        ]);
    }
}
