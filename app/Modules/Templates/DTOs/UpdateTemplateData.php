<?php

declare(strict_types=1);

namespace App\Modules\Templates\DTOs;

use App\Shared\Support\PartialAttributes;

/**
 * Modification partielle d'un modèle.
 *
 * `code` et `organizationId` sont absents de la table : le code identifie le
 * modèle pour les intégrations, et l'organisation est le périmètre. Ni l'un ni
 * l'autre ne se déplace après création.
 */
final readonly class UpdateTemplateData
{
    /** @var array<string, string> */
    private const array MAPPING = [
        'customer_id' => 'customerId',
        'service_id' => 'serviceId',
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

    public function __construct(public PartialAttributes $attributes) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(PartialAttributes::fromValidated($validated, self::MAPPING));
    }
}
