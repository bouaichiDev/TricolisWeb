<?php

declare(strict_types=1);

namespace App\Modules\Communications\Jobs;

use App\Modules\Communications\Actions\ApplyCommunicationTransition;
use App\Modules\Communications\Enums\CommunicationStatus;
use App\Modules\Communications\Exceptions\CommunicationNotEditable;
use App\Modules\Communications\Models\OrderCommunication;
use App\Modules\Communications\Services\Senders\CommunicationSenderRegistry;
use App\Shared\Support\AuditContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

/**
 * Achemine une communication mise en file.
 *
 * Le Job ne porte **que l'identifiant** : recharger la communication au moment
 * de l'exécution garantit qu'il agit sur l'état courant, et non sur une copie
 * sérialisée à la mise en file.
 *
 * Il ne relaie pas l'utilisateur : le contexte d'audit est celui du système,
 * puisque c'est le système qui envoie. L'organisation, elle, est transmise —
 * l'audit en a besoin.
 *
 * Une communication qui n'est plus `QUEUED` est ignorée sans erreur : c'est
 * l'idempotence attendue au §27, un double dispatch ne produit pas deux envois.
 */
class SendOrderCommunicationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $communicationId,
        private readonly string $organizationId,
    ) {}

    public function handle(
        ApplyCommunicationTransition $transition,
        CommunicationSenderRegistry $registry,
    ): void {
        $communication = OrderCommunication::find($this->communicationId);

        if (! $communication instanceof OrderCommunication || $communication->status !== CommunicationStatus::QUEUED) {
            return;
        }

        $context = new AuditContext($this->organizationId, null, null);

        try {
            $sending = $transition->execute(
                $communication,
                CommunicationStatus::SENDING,
                [],
                'order_communication.sending',
                $context,
            );
        } catch (CommunicationNotEditable) {
            // Une autre exécution a pris la main entre-temps : rien à faire.
            return;
        }

        $result = $registry->for($sending->channel)->send($sending);

        if ($result->successful) {
            $transition->execute($sending, CommunicationStatus::SENT, [
                'sent_at' => Carbon::now(),
                'provider_message_id' => $result->providerMessageId,
                'provider_response' => $result->providerResponse,
            ], 'order_communication.sent', $context);

            return;
        }

        $transition->execute($sending, CommunicationStatus::FAILED, [
            'failed_at' => Carbon::now(),
            'error_message' => $result->errorMessage,
            'provider_response' => $result->providerResponse,
        ], 'order_communication.failed', $context);
    }
}
