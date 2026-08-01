<?php

declare(strict_types=1);

namespace App\Modules\Drivers\DTOs;

use App\Shared\Support\PartialAttributes;

/**
 * Modification partielle d'un chauffeur.
 */
final readonly class UpdateDriverData
{
    /** @var array<string, string> */
    private const array MAPPING = [
        'legacy_id' => 'legacyId',
        'provider_id' => 'providerId',
        'user_id' => 'userId',
        'code' => 'code',
        'first_name' => 'firstName',
        'last_name' => 'lastName',
        'phone' => 'phone',
        'email' => 'email',
        'status' => 'status',
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
