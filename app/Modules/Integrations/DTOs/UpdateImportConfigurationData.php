<?php

declare(strict_types=1);

namespace App\Modules\Integrations\DTOs;

use App\Shared\Support\PartialAttributes;

/**
 * Modification partielle d'une configuration d'import.
 *
 * `customer_id` n'y figure pas : transférer une configuration d'un client à
 * l'autre reviendrait à la recréer ailleurs.
 */
final readonly class UpdateImportConfigurationData
{
    /** @var array<string, string> */
    private const array MAPPING = [
        'name' => 'name',
        'source_type' => 'sourceType',
        'file_format' => 'fileFormat',
        'mapping' => 'mapping',
        'validation_rules' => 'validationRules',
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
