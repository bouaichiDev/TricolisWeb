<?php

declare(strict_types=1);

namespace App\Modules\Orders\DTOs;

/**
 * Colis à créer, éventuellement imbriqué et rattaché à des lignes.
 *
 * Dans une création complète, les colis se référencent entre eux par une clé
 * locale au payload (`key` / `parentKey`) : les identifiants définitifs
 * n'existent pas encore au moment où le client construit sa requête.
 */
final readonly class CreatePackageData
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<array{lineKey: string|null, orderLineId: string|null, quantity: float}>  $lines
     */
    private function __construct(
        public ?string $key,
        public ?string $parentKey,
        public ?string $parentPackageId,
        public ?string $packageTypeId,
        public ?string $groupingTypeId,
        public array $attributes,
        public array $lines,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        $lines = array_map(static fn (array $line): array => [
            'lineKey' => $line['lineKey'] ?? null,
            'orderLineId' => $line['orderLineId'] ?? null,
            'quantity' => (float) $line['quantity'],
        ], $input['lines'] ?? []);

        return new self(
            $input['key'] ?? null,
            $input['parentKey'] ?? null,
            $input['parentPackageId'] ?? null,
            $input['packageTypeId'] ?? null,
            $input['groupingTypeId'] ?? null,
            array_filter([
                'barcode' => $input['barcode'] ?? null,
                'reference' => $input['reference'] ?? null,
                'description' => $input['description'] ?? null,
                'quantity' => $input['quantity'] ?? null,
                'weight' => $input['weight'] ?? null,
                'volume' => $input['volume'] ?? null,
                'length' => $input['length'] ?? null,
                'width' => $input['width'] ?? null,
                'height' => $input['height'] ?? null,
                'status' => $input['status'] ?? null,
            ], static fn ($value): bool => $value !== null),
            $lines,
        );
    }
}
