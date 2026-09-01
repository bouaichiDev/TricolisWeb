<?php

declare(strict_types=1);

namespace App\Modules\Templates\DTOs;

use App\Shared\Support\InputMapper;

/**
 * Création d'un modèle — message ou document.
 */
final readonly class CreateTemplateData
{
    /** @var array<string, string> */
    private const array MAPPING = [
        'customer_id' => 'customerId',
        'service_id' => 'serviceId',
        'code' => 'code',
        'name' => 'name',
        'channel' => 'channel',
        'template_type' => 'templateType',
        'subject_template' => 'subjectTemplate',
        'body_template' => 'bodyTemplate',
        'body_format' => 'bodyFormat',
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
