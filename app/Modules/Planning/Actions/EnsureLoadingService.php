<?php

declare(strict_types=1);

namespace App\Modules\Planning\Actions;

use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;
use App\Modules\Orders\Models\Service;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Planning\Services\DepotAddress;
use App\Modules\Planning\Services\LoadingServices;
use App\Modules\Tours\Models\Tour;
use RuntimeException;

/**
 * Le chargement qu'une commande n'a pas, créé au moment de la planifier.
 *
 * Une livraison sans chargement décrit un camion qui part chargé sans que
 * personne ne l'ait chargé : le temps de quai n'apparaît nulle part, et la
 * tournée s'annonce plus courte qu'elle ne sera. Les saisir un par un à la main
 * est le genre d'oubli qu'on ne voit qu'au dépôt, le matin.
 *
 * **L'option est par organisation**, dans ses réglages. Toutes ne travaillent
 * pas ainsi : un transporteur d'enlèvements n'a pas de quai à ouvrir, et lui
 * imposer un chargement fictif fausserait ses tournées dans l'autre sens.
 *
 * **Le chargement prend le rang suivant, jamais le premier.** Le numéro de
 * prestation en dérive — `CMD-000001-S2` — et renuméroter changerait des
 * références déjà communiquées au client. Ce qui le fait exécuter en premier,
 * c'est l'arrêt du dépôt que la tournée remonte en tête, pas ce rang.
 */
final readonly class EnsureLoadingService
{
    /** Où l'option vit dans `organizations.settings`. */
    public const string SETTING_PATH = 'planning.autoCreateLoadingService';

    /** Le chargement se pose au dépôt : sans dépôt, il n'a pas de lieu. */
    public const string REASON_NO_DEPOT = 'tour_without_depot';

    /** L'option est active mais aucun service n'est déclaré comme chargement. */
    public const string REASON_NO_LOADING_SERVICE = 'no_loading_service';

    public function __construct(
        private LoadingServices $loading,
        private DepotAddress $depot,
    ) {}

    public function isEnabled(Organization $organization): bool
    {
        return data_get($organization->settings ?? [], self::SETTING_PATH) === true;
    }

    /**
     * La commande porte-t-elle déjà un chargement ?
     *
     * Peu importe qu'il soit planifié ailleurs ou pas encore : il existe, et en
     * créer un second doublerait le temps de quai.
     */
    public function alreadyCarried(Order $order, Organization $organization): bool
    {
        $ids = $this->loading->serviceIds($organization);

        return $ids !== [] && OrderService::where('order_id', $order->id)
            ->whereIn('service_id', $ids)
            ->exists();
    }

    /**
     * Ce qui empêche de créer le chargement, ou `null`.
     *
     * Rendu **avant** toute écriture : refuser après avoir planifié la livraison
     * laisserait la commande à moitié dans la tournée.
     */
    public function refusalFor(Tour $tour, Organization $organization): ?string
    {
        if ($this->loading->serviceIds($organization) === []) {
            return self::REASON_NO_LOADING_SERVICE;
        }

        return $this->depot->for($tour) === null ? self::REASON_NO_DEPOT : null;
    }

    /**
     * Crée le chargement de cette commande, à l'adresse du dépôt.
     *
     * Il porte le poids, le volume et les colis de la commande : c'est bien
     * elle entière qu'on charge, et un chargement à zéro ne pèserait sur aucun
     * total de tournée.
     */
    public function create(Tour $tour, Order $order, Organization $organization): OrderService
    {
        $addressId = $this->depot->for($tour);
        $serviceId = $this->loading->serviceIds($organization)[0] ?? null;

        if ($addressId === null || $serviceId === null) {
            // `refusalFor` est appelé avant : y arriver signale un appel hors
            // séquence, pas une donnée manquante à gérer poliment.
            throw new RuntimeException('Le chargement ne peut pas être créé : dépôt ou service manquant.');
        }

        $service = Service::findOrFail($serviceId);
        $sequence = (int) OrderService::where('order_id', $order->id)->max('sequence') + 1;
        $minutes = (int) ($service->default_duration_minutes ?? 0);

        $loading = OrderService::create([
            'order_id' => $order->id,
            'service_id' => $serviceId,
            'address_id' => $addressId,
            'service_number' => sprintf('%s-S%d', $order->order_number, $sequence),
            'sequence' => $sequence,
            'requested_date' => $order->order_date?->toDateString() ?? now()->toDateString(),
            'requested_from' => $order->order_date,
            'requested_to' => null,
            'quantity' => 1,
            'unit' => (string) $service->unit,
            'required_time_minutes' => $minutes,
            'remaining_time_minutes' => $minutes,
            'weight' => $order->weight,
            'volume' => $order->volume,
            'package_count' => $order->package_count,
            'customer_unit_price' => 0,
            'customer_total_price' => 0,
            'provider_unit_cost' => 0,
            'provider_total_cost' => 0,
            'status' => 'ready_to_plan',
        ]);

        foreach ($order->packages as $package) {
            $loading->servicePackages()->create([
                'package_id' => $package->id,
                'quantity' => 1,
                'status' => 'pending',
            ]);
        }

        return $loading;
    }
}
