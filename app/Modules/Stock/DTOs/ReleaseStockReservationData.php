<?php

declare(strict_types=1);

namespace App\Modules\Stock\DTOs;

/**
 * Données de libération d'une réservation.
 *
 * Le statut est **fourni et validé**, jamais inventé : le §23 demande de le
 * « modifier selon une valeur fournie et validée sans inventer d'enum ».
 */
final readonly class ReleaseStockReservationData
{
    public function __construct(public string $status) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(status: $validated['status']);
    }
}
