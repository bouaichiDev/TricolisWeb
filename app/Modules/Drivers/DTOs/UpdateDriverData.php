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
        'provider_id' => 'providerId',
        'address_id' => 'addressId',
        'contact_id' => 'contactId',
        'code' => 'code',
        'name' => 'name',
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
