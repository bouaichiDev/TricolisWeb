<?php

declare(strict_types=1);

namespace App\Modules\Communications\Services;

/**
 * Destinataire résolu — trois champs figés dans la communication.
 *
 * Aucune table `CommunicationRecipient` n'est créée (§22) : le diagramme porte
 * ces informations directement sur `OrderCommunication`, en snapshot.
 */
final readonly class ResolvedRecipient
{
    public function __construct(
        public string $name,
        public ?string $email,
        public ?string $phone,
    ) {}

    /**
     * @return array<string, string|null>
     */
    public function toAttributes(): array
    {
        return [
            'recipient_name' => $this->name,
            'recipient_email' => $this->email,
            'recipient_phone' => $this->phone,
        ];
    }
}
