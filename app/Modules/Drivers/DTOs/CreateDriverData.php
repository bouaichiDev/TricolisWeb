<?php

declare(strict_types=1);

namespace App\Modules\Drivers\DTOs;

/**
 * Données de création d'un chauffeur.
 */
final readonly class CreateDriverData
{
    public function __construct(
        public string $providerId,
        public string $code,
        public string $firstName,
        public string $lastName,
        public string $status,
        public ?string $userId = null,
        public ?string $phone = null,
        public ?string $email = null,
        public ?int $legacyId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            providerId: $validated['providerId'],
            code: $validated['code'],
            firstName: $validated['firstName'],
            lastName: $validated['lastName'],
            status: $validated['status'],
            userId: $validated['userId'] ?? null,
            phone: $validated['phone'] ?? null,
            email: $validated['email'] ?? null,
            legacyId: $validated['legacyId'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'legacy_id' => $this->legacyId,
            'provider_id' => $this->providerId,
            'user_id' => $this->userId,
            'code' => $this->code,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'phone' => $this->phone,
            'email' => $this->email,
            'status' => $this->status,
        ];
    }
}
