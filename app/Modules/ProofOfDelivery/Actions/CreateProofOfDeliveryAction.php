<?php

declare(strict_types=1);

namespace App\Modules\ProofOfDelivery\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\ProofOfDelivery\DTOs\CreateProofOfDeliveryData;
use App\Modules\ProofOfDelivery\Models\ProofOfDelivery;
use App\Modules\Tracking\Services\TrackingScopeGuard;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Crée une preuve de livraison.
 *
 * Les documents de signature et de photo doivent appartenir à l'organisation de
 * la commande : une signature venue d'ailleurs ne prouve rien. Ils sont créés
 * au préalable par le module Documents, et seulement référencés ici.
 *
 * **Aucun changement de statut n'est déclenché.** Le §11 l'interdit sans règle
 * explicite déjà validée : aucune n'existe dans les Phases 1 à 4 reliant une
 * preuve au statut d'une commande, d'un service ou d'une tournée. En inventer
 * une ferait avancer un workflow que personne n'a défini.
 */
final readonly class CreateProofOfDeliveryAction
{
    public function __construct(
        private TrackingScopeGuard $guard,
        private WriteAuditLog $audit,
    ) {}

    public function execute(CreateProofOfDeliveryData $data, AuditContext $context): ProofOfDelivery
    {
        $order = $this->guard->order($data->orderId, $context->organizationId);

        if ($data->orderServiceId !== null) {
            $this->guard->orderService($data->orderServiceId, $order);
        }

        if ($data->tourStopId !== null) {
            $this->guard->tourStop($data->tourStopId, null, $context->organizationId);
        }

        foreach ([
            'signatureDocumentId' => $data->signatureDocumentId,
            'photoDocumentId' => $data->photoDocumentId,
        ] as $field => $documentId) {
            if ($documentId !== null) {
                $this->guard->document($documentId, $order->organization_id, $field);
            }
        }

        return DB::transaction(function () use ($data, $context): ProofOfDelivery {
            $proof = ProofOfDelivery::create($data->toAttributes($context->user?->id));

            $this->audit->execute(
                $context->organizationId,
                $context->user,
                'proof_of_delivery.created',
                $proof,
                null,
                $proof->only(['order_id', 'recipient_name', 'delivered_at']),
                null,
                $context->ipAddress,
            );

            return $proof;
        });
    }
}
