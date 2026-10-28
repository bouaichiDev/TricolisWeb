<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Provider;

use App\Modules\ProofOfDelivery\Models\ProofOfDelivery;
use App\Modules\ProviderPortal\Models\ProviderUser;
use App\Modules\ProviderPortal\Services\CurrentProviderContext;
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
    /** @var array<string, list<string>> comptes du portail, par fournisseur */
    private static array $members = [];

    /**
     * @return list<string>
     */
    private static function providerUserIds(): array
    {
        $providerId = app(CurrentProviderContext::class)->providerId();

        return self::$members[$providerId] ??= ProviderUser::where('provider_id', $providerId)->pluck('user_id')->all();
    }

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
            // La remarque d'une preuve saisie au back-office peut être interne :
            // seule celle d'une preuve saisie par ce fournisseur est rendue.
            'remark' => $this->created_by !== null && in_array($this->created_by, self::providerUserIds(), true) ? $this->remark : null,
            'deliveredAt' => $this->delivered_at?->toIso8601String(),
            'signatureDocumentId' => $this->signature_document_id,
            'photoDocumentId' => $this->photo_document_id,
        ];
    }
}
