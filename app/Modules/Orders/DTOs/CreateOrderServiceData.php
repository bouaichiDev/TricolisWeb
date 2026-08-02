<?php

declare(strict_types=1);

namespace App\Modules\Orders\DTOs;

/**
 * Service de commande à créer, avec son adresse, ses contacts et ses colis.
 *
 * C'est l'unité de planification du modèle : l'adresse et le créneau demandé
 * sont portés ici, pas par la commande.
 */
final readonly class CreateOrderServiceData
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<array<string, mixed>>  $contacts
     * @param  list<array{packageKey: string|null, packageId: string|null, quantity: float, handlingInstructions: string|null, status: string|null}>  $packages
     */
    private function __construct(
        public string $serviceId,
        public string $addressId,
        public array $attributes,
        public array $contacts,
        public array $packages,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        $packages = array_map(static fn (array $package): array => [
            'packageKey' => $package['packageKey'] ?? null,
            'packageId' => $package['packageId'] ?? null,
            'quantity' => (float) ($package['quantity'] ?? 1),
            'handlingInstructions' => $package['handlingInstructions'] ?? null,
            'status' => $package['status'] ?? null,
        ], $input['packages'] ?? []);

        return new self(
            $input['serviceId'],
            $input['addressId'],
            array_filter([
                'service_number' => $input['serviceNumber'] ?? null,
                'sequence' => $input['sequence'] ?? null,
                'requested_date' => $input['requestedDate'] ?? null,
                'requested_from' => $input['requestedFrom'] ?? null,
                'requested_to' => $input['requestedTo'] ?? null,
                'quantity' => $input['quantity'] ?? null,
                'unit' => $input['unit'] ?? null,
                'required_time_minutes' => $input['requiredTimeMinutes'] ?? null,
                'remaining_time_minutes' => $input['remainingTimeMinutes'] ?? null,
                'weight' => $input['weight'] ?? null,
                'volume' => $input['volume'] ?? null,
                'package_count' => $input['packageCount'] ?? null,
                'customer_unit_price' => $input['customerUnitPrice'] ?? null,
                'customer_total_price' => $input['customerTotalPrice'] ?? null,
                'provider_unit_cost' => $input['providerUnitCost'] ?? null,
                'provider_total_cost' => $input['providerTotalCost'] ?? null,
                'instructions' => $input['instructions'] ?? null,
                'status' => $input['status'] ?? null,
            ], static fn ($value): bool => $value !== null),
            $input['contacts'] ?? [],
            $packages,
        );
    }
}
