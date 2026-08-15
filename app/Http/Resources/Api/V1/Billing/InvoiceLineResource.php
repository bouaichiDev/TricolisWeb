<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Billing;

use App\Modules\Billing\Models\InvoiceLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ligne de facture.
 *
 * @mixin InvoiceLine
 */
class InvoiceLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoiceId' => $this->invoice_id,
            'orderServiceId' => $this->order_service_id,
            'orderId' => $this->order_id,
            'lineNumber' => $this->line_number,
            'serviceCode' => $this->service_code,
            'description' => $this->description,
            'customerOrderReference' => $this->customer_order_reference,
            'quantity' => $this->quantity,
            'unitPrice' => $this->unit_price,
            'discountRate' => $this->discount_rate,
            'taxRate' => $this->tax_rate,
            'totalExcludingTax' => $this->total_excluding_tax,
            'totalIncludingTax' => $this->total_including_tax,
            'serviceCompletedAt' => $this->service_completed_at?->toIso8601String(),
            'status' => $this->status,
            'addressSnapshot' => new InvoiceLineAddressSnapshotResource($this->whenLoaded('addressSnapshot')),
        ];
    }
}
