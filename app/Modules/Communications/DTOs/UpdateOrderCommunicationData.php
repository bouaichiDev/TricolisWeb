<?php

declare(strict_types=1);

namespace App\Modules\Communications\DTOs;

use App\Shared\Support\PartialAttributes;

/**
 * Modification d'une communication **en brouillon**.
 *
 * La liste est courte à dessein : ni `orderId`, ni `channel`, ni `templateId`,
 * ni aucun champ d'exécution. Changer de commande ou de canal après création
 * reviendrait à créer une autre communication ; changer un horodatage
 * réécrirait l'histoire.
 */
final readonly class UpdateOrderCommunicationData
{
    /** @var array<string, string> */
    private const array MAPPING = [
        'recipient_name' => 'recipientName',
        'recipient_email' => 'recipientEmail',
        'recipient_phone' => 'recipientPhone',
        'subject' => 'subject',
        'body' => 'body',
        'template_variables' => 'templateVariables',
        'scheduled_at' => 'scheduledAt',
    ];

    public function __construct(public PartialAttributes $attributes) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(PartialAttributes::fromValidated($validated, self::MAPPING));
    }
}
