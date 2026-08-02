<?php

declare(strict_types=1);

namespace App\Modules\Communications\DTOs;

use App\Modules\Communications\Enums\CommunicationChannel;
use App\Modules\Communications\Enums\CommunicationTemplateType;
use App\Modules\Communications\Enums\RecipientRole;

/**
 * Création d'une communication de commande.
 *
 * Le DTO ne porte que les **entrées** : ni statut, ni horodatage, ni réponse
 * fournisseur. Ces champs sont produits par l'Action et par le Job — les
 * accepter en entrée permettrait de déclarer envoyé ce qui ne l'est pas.
 *
 * `subject` et `body` ne sont acceptés que sans template : avec un template, ils
 * sont le résultat du rendu, jamais une saisie.
 */
final readonly class CreateOrderCommunicationData
{
    /**
     * @param  array<string, mixed>|null  $templateVariables
     */
    public function __construct(
        public string $orderId,
        public CommunicationChannel $channel,
        public CommunicationTemplateType $communicationType,
        public RecipientRole $recipientRole,
        public ?string $templateId,
        public ?string $communicationRuleId,
        public ?string $subject,
        public ?string $body,
        public ?array $templateVariables,
        public ?string $scheduledAt,
        public ?string $recipientName,
        public ?string $recipientEmail,
        public ?string $recipientPhone,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            orderId: $validated['orderId'],
            channel: CommunicationChannel::from($validated['channel']),
            communicationType: CommunicationTemplateType::from($validated['communicationType']),
            recipientRole: RecipientRole::from($validated['recipientRole']),
            templateId: $validated['templateId'] ?? null,
            communicationRuleId: $validated['communicationRuleId'] ?? null,
            subject: $validated['subject'] ?? null,
            body: $validated['body'] ?? null,
            templateVariables: $validated['templateVariables'] ?? null,
            scheduledAt: $validated['scheduledAt'] ?? null,
            recipientName: $validated['recipientName'] ?? null,
            recipientEmail: $validated['recipientEmail'] ?? null,
            recipientPhone: $validated['recipientPhone'] ?? null,
        );
    }

    /**
     * Coordonnées fournies explicitement — utilisées pour le seul rôle `CUSTOM`.
     *
     * @return array{name?: string|null, email?: string|null, phone?: string|null}
     */
    public function explicitRecipient(): array
    {
        return [
            'name' => $this->recipientName,
            'email' => $this->recipientEmail,
            'phone' => $this->recipientPhone,
        ];
    }
}
