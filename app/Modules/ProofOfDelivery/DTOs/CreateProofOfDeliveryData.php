<?php

declare(strict_types=1);

namespace App\Modules\ProofOfDelivery\DTOs;

/**
 * Données de création d'une preuve de livraison.
 *
 * Signature et photo sont des **identifiants de documents déjà créés**, jamais
 * des fichiers : l'upload passe par le module Documents. Les deux sont
 * facultatifs, comme le pose `Document "0..1" -- "0..*" ProofOfDelivery`.
 */
final readonly class CreateProofOfDeliveryData
{
    public function __construct(
        public string $orderId,
        public string $recipientName,
        public string $deliveredAt,
        public ?string $orderServiceId = null,
        public ?string $tourStopId = null,
        public ?string $signatureDocumentId = null,
        public ?string $photoDocumentId = null,
        public ?string $remark = null,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            orderId: $validated['orderId'],
            recipientName: $validated['recipientName'],
            deliveredAt: $validated['deliveredAt'],
            orderServiceId: $validated['orderServiceId'] ?? null,
            tourStopId: $validated['tourStopId'] ?? null,
            signatureDocumentId: $validated['signatureDocumentId'] ?? null,
            photoDocumentId: $validated['photoDocumentId'] ?? null,
            remark: $validated['remark'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(?string $createdBy): array
    {
        return [
            'order_id' => $this->orderId,
            'order_service_id' => $this->orderServiceId,
            'tour_stop_id' => $this->tourStopId,
            'recipient_name' => $this->recipientName,
            'signature_document_id' => $this->signatureDocumentId,
            'photo_document_id' => $this->photoDocumentId,
            'remark' => $this->remark,
            'delivered_at' => $this->deliveredAt,
            'created_by' => $createdBy,
        ];
    }
}
