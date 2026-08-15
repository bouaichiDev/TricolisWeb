<?php

declare(strict_types=1);

namespace App\Modules\Communications\DTOs;

use App\Shared\Support\InputMapper;

/**
 * Création d'une règle de communication.
 */
final readonly class CreateCommunicationRuleData
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

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(public array $attributes) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated, string $organizationId): self
    {
        return new self([
            'organization_id' => $organizationId,
            ...InputMapper::map($validated, self::MAPPING),
        ]);
    }
}
