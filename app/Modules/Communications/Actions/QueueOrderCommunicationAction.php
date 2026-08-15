<?php

declare(strict_types=1);

namespace App\Modules\Communications\Actions;

use App\Modules\Communications\Enums\CommunicationStatus;
use App\Modules\Communications\Jobs\SendOrderCommunicationJob;
use App\Modules\Communications\Models\OrderCommunication;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Carbon;

/**
 * Met une communication en file d'envoi.
 *
 * Le Job n'est dépêché **qu'après validation de la transition** : si la
 * communication est déjà partie ou annulée, `ApplyCommunicationTransition` lève
 * avant que quoi que ce soit ne parte.
 *
 * `QUEUE_CONNECTION=database` est configuré : la condition du §25 est remplie.
 * Avec le driver `sync`, en test, le Job s'exécute immédiatement — le
 * comportement reste celui décrit.
 */
final readonly class QueueOrderCommunicationAction
{
    public function __construct(private ApplyCommunicationTransition $transition) {}

    public function execute(OrderCommunication $communication, AuditContext $context): OrderCommunication
    {
        $queued = $this->transition->execute(
            $communication,
            CommunicationStatus::QUEUED,
            ['queued_at' => Carbon::now()],
            'order_communication.queued',
            $context,
        );

        SendOrderCommunicationJob::dispatch($queued->id, $context->organizationId);

        return $queued;
    }
}
