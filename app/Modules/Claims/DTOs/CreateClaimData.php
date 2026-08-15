<?php

declare(strict_types=1);

namespace App\Modules\Claims\DTOs;

/**
 * Données de création d'une réclamation.
 *
 * Les champs de résolution — `decision`, `followUp`, `result`, `cost`,
 * `closedAt` — ne figurent pas ici : le §15 interdit de les exiger à la
 * création, et une réclamation naît ouverte. Ils se renseignent par `PATCH`, au
 * fil de l'instruction.
 *
 * `responsibleUserId` est en revanche acceptable dès l'ouverture : affecter un
 * dossier n'est pas le résoudre.
 */
final readonly class CreateClaimData
{
    public function __construct(
        public string $customerId,
        public string $title,
        public string $claimType,
        public string $status,
        public ?string $orderId = null,
        public ?string $orderServiceId = null,
        public ?string $tourId = null,
        public ?string $description = null,
        public ?string $cause = null,
        public ?string $responsibleUserId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            customerId: $validated['customerId'],
            title: $validated['title'],
            claimType: $validated['claimType'],
            status: $validated['status'],
            orderId: $validated['orderId'] ?? null,
            orderServiceId: $validated['orderServiceId'] ?? null,
            tourId: $validated['tourId'] ?? null,
            description: $validated['description'] ?? null,
            cause: $validated['cause'] ?? null,
            responsibleUserId: $validated['responsibleUserId'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(string $organizationId, ?string $createdBy, string $createdAt): array
    {
        return [
            'organization_id' => $organizationId,
            'customer_id' => $this->customerId,
            'order_id' => $this->orderId,
            'order_service_id' => $this->orderServiceId,
            'tour_id' => $this->tourId,
            'title' => $this->title,
            'description' => $this->description,
            'claim_type' => $this->claimType,
            'cause' => $this->cause,
            'status' => $this->status,
            'created_by' => $createdBy,
            'responsible_user_id' => $this->responsibleUserId,
            'created_at' => $createdAt,
        ];
    }
}
