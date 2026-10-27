<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Services\Tracking;

use App\Modules\CustomerPortal\Services\CustomerStatusLabels;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Services\OrganizationLogo;
use App\Shared\Database\MorphMap;
use Illuminate\Support\Facades\Config;

/**
 * La réponse **minimale** du lien public (§26-§28).
 *
 * Chaque bloc est soumis au réglage du transporteur ; un bloc fermé vaut
 * `null`. N'en sortent jamais : identifiants internes, coûts, facturation,
 * utilisateurs, audit, tournée, chauffeur, véhicule, autres arrêts — ni la rue
 * de l'adresse ni le nom du réceptionnaire. La commune suffit à reconnaître sa
 * livraison.
 */
final readonly class PublicTrackingPresenter
{
    public function __construct(
        private CustomerStatusLabels $labels,
        private CustomerTrackingTimeline $timeline,
        private PublicTrackingSettings $settings,
        private OrganizationLogo $logo,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function present(OrderTrackingSnapshot $snapshot, Organization $organization): array
    {
        $order = $snapshot->order;
        $show = $this->settings->visibility($organization);
        $eta = $snapshot->eta();
        unset($eta['orderServiceId']);

        return [
            'organization' => ['name' => $organization->name, 'hasLogo' => $this->logo->exists($organization)],
            'visibility' => $show,
            'reference' => $show['showReference'] ? ['reference' => $order->order_number, 'customerReference' => $order->customer_reference] : null,
            'status' => $show['showStatus'] ? $this->labels->present(MorphMap::ORDER, $order->status) : null,
            'progress' => $show['showStatus'] ? $snapshot->progress() : null,
            'isActive' => $snapshot->active,
            'refreshSeconds' => $snapshot->active ? Config::integer('customer_portal.tracking.refresh_seconds', 45) : null,
            'services' => $show['showDeliveryWindow'] ? array_map($this->service(...), $snapshot->services) : null,
            'timeline' => $show['showTimeline'] ? $this->timeline($snapshot) : null,
            'eta' => $show['showEta'] ? $eta : null,
            'map' => $show['showMap'] ? $this->map($snapshot) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function service(TrackedService $tracked): array
    {
        $address = $tracked->service->address;

        return [
            'name' => $tracked->name(),
            'status' => $this->labels->present(MorphMap::ORDER_SERVICE, $tracked->service->status)['label'],
            'completed' => $tracked->completed(),
            'slot' => $tracked->slot,
            'postalCode' => $address?->postal_code,
            'city' => $address?->city,
        ];
    }

    /**
     * @return list<array{type: string, title: string, description: string|null, at: string, state: string}>
     */
    private function timeline(OrderTrackingSnapshot $snapshot): array
    {
        return array_map(static function (array $item): array {
            unset($item['orderServiceId']);

            return $item;
        }, $this->timeline->for($snapshot, public: true));
    }

    /**
     * @return array<string, mixed>
     */
    private function map(OrderTrackingSnapshot $snapshot): array
    {
        $position = $snapshot->position();
        $points = [];

        foreach ($snapshot->services as $tracked) {
            $address = $tracked->service->address;

            if ($address?->latitude !== null && $address->longitude !== null) {
                $points[] = ['label' => $tracked->name(), 'latitude' => (float) $address->latitude, 'longitude' => (float) $address->longitude, 'completed' => $tracked->completed()];
            }
        }

        return [
            'available' => $position !== null,
            'currentPosition' => $position === null ? null : ['latitude' => $position['latitude'], 'longitude' => $position['longitude'], 'recordedAt' => $position['recordedAt']],
            'points' => $points,
        ];
    }
}
