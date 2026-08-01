<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Billing;

use App\Modules\Billing\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Facture vue depuis une liste : aucune ligne chargée, seulement leur compteur.
 *
 * @mixin Invoice
 */
class InvoiceListResource extends JsonResource
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
            'status' => $this->status,
            'createdAt' => $this->created_at?->toIso8601String(),
            'customerName' => $this->whenLoaded('customer', fn () => $this->customer->name),
            'lineCount' => $this->whenCounted('lines'),
        ];
    }
}
