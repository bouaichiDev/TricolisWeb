<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Billing;

use App\Http\Resources\Api\V1\Customers\CustomerCompactResource;
use App\Modules\Billing\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Détail d'une facture.
 *
 * @mixin Invoice
 */
class InvoiceDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organizationId' => $this->organization_id,
            'customerId' => $this->customer_id,
            'invoiceNumber' => $this->invoice_number,
            'invoiceDate' => $this->invoice_date?->toDateString(),
            'periodFrom' => $this->period_from?->toDateString(),
            'periodTo' => $this->period_to?->toDateString(),
            'currencyCode' => $this->currency_code,
            'subtotal' => $this->subtotal,
            'taxTotal' => $this->tax_total,
            'total' => $this->total,
            'externalReference' => $this->external_reference,
            'remark' => $this->remark,
            'status' => $this->status,
            'createdAt' => $this->created_at?->toIso8601String(),
            'customer' => new CustomerCompactResource($this->whenLoaded('customer')),
            'lines' => InvoiceLineResource::collection($this->whenLoaded('lines')),
            'lineCount' => $this->whenCounted('lines'),
        ];
    }
}
