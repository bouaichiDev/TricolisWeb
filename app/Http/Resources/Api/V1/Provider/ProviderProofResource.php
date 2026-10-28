<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Provider;

use App\Modules\ProofOfDelivery\Models\ProofOfDelivery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Une preuve de livraison telle que le fournisseur la voit : le service livré
 * (par son numéro, jamais la commande), le réceptionnaire, l'heure et les
 * documents de signature et de photo, téléchargeables par le portail.
 *
 * @mixin ProofOfDelivery
 */
class ProviderProofResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'orderServiceId' => $this->order_service_id,
            'serviceNumber' => $this->orderService?->service_number,
            'recipientName' => $this->recipient_name,
            'remark' => $this->remark,
            'deliveredAt' => $this->delivered_at?->toIso8601String(),
            'signatureDocumentId' => $this->signature_document_id,
            'photoDocumentId' => $this->photo_document_id,
        ];
    }
}
