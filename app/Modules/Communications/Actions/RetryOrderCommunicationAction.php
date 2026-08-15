<?php

declare(strict_types=1);

namespace App\Modules\Communications\Actions;

use App\Modules\Communications\Enums\CommunicationStatus;
use App\Modules\Communications\Exceptions\CommunicationNotEditable;
use App\Modules\Communications\Jobs\SendOrderCommunicationJob;
use App\Modules\Communications\Models\OrderCommunication;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Carbon;

/**
 * Relance une communication échouée.
 *
 * `FAILED → QUEUED` est la seule sortie d'un échec dans le graphe de l'enum :
 * relancer une communication envoyée la dupliquerait chez le destinataire.
 *
 * Aucune colonne n'est ajoutée (§29). L'erreur précédente et sa date sont
 * effacées : elles décrivaient une tentative qui n'est plus l'état courant, et
 * l'audit en garde la trace.
 */
final readonly class RetryOrderCommunicationAction
{
    public function __construct(private ApplyCommunicationTransition $transition) {}

    public function execute(OrderCommunication $communication, AuditContext $context): OrderCommunication
    {
        // `DRAFT → QUEUED` est un envoi, pas une relance : il a sa propre
        // permission. Sans ce refus, `retry` la contournerait.
        if ($communication->status !== CommunicationStatus::FAILED) {
            throw CommunicationNotEditable::forTransition($communication->status, CommunicationStatus::QUEUED);
        }

        $queued = $this->transition->execute(
            $communication,
            CommunicationStatus::QUEUED,
            ['queued_at' => Carbon::now(), 'failed_at' => null, 'error_message' => null],
            'order_communication.retried',
            $context,
        );

        SendOrderCommunicationJob::dispatch($queued->id, $context->organizationId);

        return $queued;
    }
}
