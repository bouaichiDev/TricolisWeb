<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Billing;

use App\Modules\Billing\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Facture réduite à ce qu'affiche un rappel.
 *
 * @mixin Invoice
 */
class InvoiceCompactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoiceNumber' => $this->invoice_number,
            'invoiceDate' => $this->invoice_date?->toDateString(),
            'total' => $this->total,
            'currencyCode' => $this->currency_code,
            'status' => $this->status,
        ];
    }
}
