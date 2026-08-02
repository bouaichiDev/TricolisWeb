<?php

declare(strict_types=1);

namespace App\Modules\Orders\DTOs;

/**
 * Payload complet d'une commande : en-tête, lignes, colis et services.
 */
final readonly class CreateOrderData
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<CreateOrderLineData>  $lines
     * @param  list<CreatePackageData>  $packages
     * @param  list<CreateOrderServiceData>  $services
     */
    private function __construct(
        public string $customerId,
        public string $agencyId,
        public ?string $depotId,
        public array $attributes,
        public array $lines,
        public array $packages,
        public array $services,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        return new self(
            $input['customerId'],
            $input['agencyId'],
            $input['depotId'] ?? null,
            array_filter([
                'order_number' => $input['orderNumber'] ?? null,
                'external_reference' => $input['externalReference'] ?? null,
                'customer_reference' => $input['customerReference'] ?? null,
                'order_type' => $input['orderType'] ?? null,
                'group_code' => $input['groupCode'] ?? null,
                'order_date' => $input['orderDate'] ?? null,
                'source' => $input['source'] ?? null,
                'internal_remark' => $input['internalRemark'] ?? null,
                'worker_remark' => $input['workerRemark'] ?? null,
                'currency_code' => $input['currencyCode'] ?? null,
                'status' => $input['status'] ?? null,
            ], static fn ($value): bool => $value !== null),
            array_map(CreateOrderLineData::fromArray(...), $input['lines'] ?? []),
            array_map(CreatePackageData::fromArray(...), $input['packages'] ?? []),
            array_map(CreateOrderServiceData::fromArray(...), $input['services'] ?? []),
        );
    }
}
