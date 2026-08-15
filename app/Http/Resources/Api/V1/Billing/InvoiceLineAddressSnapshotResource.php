<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Billing;

use App\Modules\Billing\Models\InvoiceLineAddressSnapshot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Adresse figée d'une ligne de facture.
 *
 * @mixin InvoiceLineAddressSnapshot
 */
class InvoiceLineAddressSnapshotResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoiceLineId' => $this->invoice_line_id,
            'addressCode' => $this->address_code,
            'name' => $this->name,
            'addressLine1' => $this->address_line1,
            'addressLine2' => $this->address_line2,
            'postalCode' => $this->postal_code,
            'city' => $this->city,
            'country' => $this->country,
        ];
    }
}
