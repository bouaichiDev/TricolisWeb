<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Services\Stock;

use App\Modules\CustomerPortal\Services\CurrentCustomerContext;

/**
 * Ce qu'un membre du portail peut faire d'un de ses articles (§23).
 *
 * `canRequestChange` suit `customer.stock.adjustment_requests.create` : la
 * demande de correction (`StockAdjustmentRequest`, ajoutée au diagramme le
 * 2026-09-30) ne touche jamais au stock — elle attend la décision du
 * transporteur.
 */
final readonly class CustomerStockCapabilities
{
    public function __construct(private CurrentCustomerContext $context) {}

    /**
     * @return array{canView: bool, canEditMetadata: bool, canViewMovements: bool, canViewReservations: bool, canRequestChange: bool}
     */
    public function forItem(): array
    {
        return [
            'canView' => $this->context->can('customer.stock.view'),
            'canEditMetadata' => $this->context->can('customer.stock.items.update'),
            'canViewMovements' => $this->context->can('customer.stock.movements.view'),
            'canViewReservations' => $this->context->can('customer.stock.reservations.view'),
            'canRequestChange' => $this->context->can('customer.stock.adjustment_requests.create'),
        ];
    }
}
