<?php

declare(strict_types=1);

namespace App\Modules\Communications\Services\Senders;

use App\Modules\Communications\Models\OrderCommunication;

/**
 * Contrat des transporteurs, un par canal.
 *
 * Un transporteur **ne touche jamais la base** : il reçoit la communication,
 * tente l'acheminement et retourne un résultat. Le Job seul écrit le statut et
 * les horodatages. C'est ce qui permet de le remplacer par un fake dans les
 * tests sans toucher au reste (§26).
 */
interface CommunicationSender
{
    public function send(OrderCommunication $communication): SenderResult;
}
