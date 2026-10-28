<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Provider;

use App\Modules\Tours\Models\TourStopService;
use App\Shared\Enums\ContactRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un service à réaliser à un arrêt, réduit au besoin opérationnel : numéro de
 * service (celui des étiquettes), nature, créneau, poids, volume, colis,
 * consignes et contacts sur place.
 *
 * Jamais de prix, de coût, de numéro de commande, de référence externe ni de
 * client : une tournée regroupe plusieurs clients, et le fournisseur n'a pas
 * à connaître leurs affaires.
 *
 * @mixin TourStopService
 */
class ProviderStopServiceResource extends JsonResource
{
    /** Les contacts utiles sur place ; jamais la facturation ni « autre ». */
    private const array ON_SITE = [ContactRole::LOAD, ContactRole::DELIVERY, ContactRole::OPERATIONS, ContactRole::EMERGENCY];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $service = $this->orderService;

        return [
            'id' => $this->id,
            'status' => (string) ($this->status instanceof \BackedEnum ? $this->status->value : $this->status),
            'serviceNumber' => $service?->service_number,
            'serviceName' => $service?->service?->name,
            'requestedFrom' => $service?->requested_from?->toIso8601String(),
            'requestedTo' => $service?->requested_to?->toIso8601String(),
            'weight' => $service === null ? null : (string) $service->weight,
            'volume' => $service === null ? null : (string) $service->volume,
            'packageCount' => $service?->package_count,
            'instructions' => $service?->instructions,
            'contacts' => $service === null ? [] : $service->contacts
                ->filter(static fn ($contact): bool => in_array($contact->contact_role, self::ON_SITE, true))
                ->map(static fn ($contact): array => [
                    'firstName' => $contact->first_name_snapshot,
                    'lastName' => $contact->last_name_snapshot,
                    'phone' => $contact->phone_snapshot,
                    'mobile' => $contact->mobile_snapshot,
                ])->values()->all(),
        ];
    }
}
