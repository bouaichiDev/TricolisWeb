<?php

declare(strict_types=1);

namespace App\Modules\Communications\DTOs;

/**
 * Ajout d'une pièce jointe.
 *
 * Seul le document est fourni : les deux snapshots sont **dérivés** de lui à
 * l'ajout. Les accepter en entrée permettrait de joindre un document en le
 * présentant sous un autre nom.
 */
final readonly class AddCommunicationAttachmentData
{
    public function __construct(public string $documentId) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self($validated['documentId']);
    }
}
