<?php

declare(strict_types=1);

namespace App\Modules\Communications\DTOs;

use App\Shared\Support\InputMapper;

/**
 * Création d'un modèle de message.
 */
final readonly class CreateCommunicationTemplateData
{
    /** @var array<string, string> */
    private const array MAPPING = [
        'service_id' => 'serviceId',
        'code' => 'code',
        'name' => 'name',
        'channel' => 'channel',
        'template_type' => 'templateType',
        'subject_template' => 'subjectTemplate',
        'body_template' => 'bodyTemplate',
        'language' => 'language',
        'available_variables' => 'availableVariables',
        'is_default' => 'isDefault',
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
