<?php

declare(strict_types=1);

namespace App\Modules\Communications\DTOs;

use App\Shared\Support\PartialAttributes;

/**
 * Modification partielle d'une règle de communication.
 */
final readonly class UpdateCommunicationRuleData
{
    /** @var array<string, string> */
    private const array MAPPING = [
        'service_id' => 'serviceId',
        'template_id' => 'templateId',
        'event_type' => 'eventType',
        'recipient_role' => 'recipientRole',
        'delay_value' => 'delayValue',
        'delay_unit' => 'delayUnit',
        'conditions' => 'conditions',
        'is_automatic' => 'isAutomatic',
        'is_active' => 'isActive',
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
