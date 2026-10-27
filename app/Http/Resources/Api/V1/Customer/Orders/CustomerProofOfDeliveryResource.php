<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Customer\Orders;

use App\Modules\Documents\Models\Document;
use App\Modules\ProofOfDelivery\Models\ProofOfDelivery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Une preuve de livraison, **en lecture seule**, pour le client (§17-§19).
 *
 * Signature et photo sont des `Document` : n'en sortent que l'identifiant et
 * les métadonnées — jamais le chemin de stockage. Leur contenu se lit par
 * `GET /documents/{id}/download`, qui revérifie le chemin d'accès. Ni arrêt
 * de tournée ni auteur interne.
 *
 * @mixin ProofOfDelivery
 */
class CustomerProofOfDeliveryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $service = $this->orderService;

        return [
            'id' => $this->id,
            'orderId' => $this->order_id,
            'orderServiceId' => $this->order_service_id,
            'service' => $service === null ? null : [
                'id' => $service->id,
                'serviceNumber' => $service->service_number,
                'name' => $service->service?->name,
            ],
            'recipientName' => $this->recipient_name,
            'remark' => $this->remark,
            'deliveredAt' => $this->delivered_at?->toIso8601String(),
            'signature' => self::document($this->signatureDocument),
            'photo' => self::document($this->photoDocument),
        ];
    }

    /**
     * @return array{id: string, fileName: string, mimeType: string, size: int}|null
     */
    private static function document(?Document $document): ?array
    {
        return $document === null ? null : [
            'id' => $document->id,
            'fileName' => $document->file_name,
            'mimeType' => $document->mime_type,
            'size' => (int) $document->size,
        ];
    }
}
