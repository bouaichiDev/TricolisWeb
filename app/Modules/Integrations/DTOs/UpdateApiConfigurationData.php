<?php

declare(strict_types=1);

namespace App\Modules\Integrations\DTOs;

use App\Shared\Support\PartialAttributes;

/**
 * Modification partielle d'un accès API.
 *
 * Ni `api_key_hash`, ni `last_used_at` : la clé se renouvelle par
 * `POST /rotate-key`, et la date d'usage est posée par la vérification de clé,
 * pas par un opérateur.
 */
final readonly class UpdateApiConfigurationData
{
    /** @var array<string, string> */
    private const array MAPPING = [
        'name' => 'name',
        'allowed_ips' => 'allowedIps',
        'permissions' => 'permissions',
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
