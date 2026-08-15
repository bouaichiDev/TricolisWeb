<?php

declare(strict_types=1);

namespace App\Modules\Tours\Services;

use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\Depot;
use App\Modules\Drivers\Models\Driver;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Orders\Models\OrderService;
use App\Modules\Packages\Models\Package;
use App\Modules\Providers\Models\Provider;
use Illuminate\Validation\ValidationException;

/**
 * Vérifie que les références d'une tournée sont cohérentes et dans le périmètre.
 *
 * Un `exists:agencies,id` seul laisserait passer l'agence d'une autre
 * organisation, et un chauffeur d'un autre fournisseur. Tous les contrôles du
 * §8 et du §19 passent par ici, en un seul endroit, appelé par les Actions —
 * qui restent ainsi sûres même invoquées hors HTTP.
 */
final readonly class TourScopeGuard
{
    public function agency(string $agencyId, string $organizationId): Agency
    {
        $agency = Agency::where('organization_id', $organizationId)->whereKey($agencyId)->first();

        return $agency ?? $this->fail('agencyId', 'Cette agence n’appartient pas à l’organisation active.');
    }

    /**
     * Le dépôt appartient toujours au transporteur via son agence : il doit
     * donc être rattaché à l'agence de la tournée, pas seulement exister.
     */
    public function depot(string $depotId, string $agencyId): Depot
    {
        $depot = Depot::where('agency_id', $agencyId)->whereKey($depotId)->first();

        return $depot ?? $this->fail('depotId', 'Ce dépôt n’est pas rattaché à l’agence de la tournée.');
    }

    public function provider(string $providerId, string $organizationId): Provider
    {
        $provider = Provider::where('organization_id', $organizationId)->whereKey($providerId)->first();

        return $provider ?? $this->fail('providerId', 'Ce fournisseur n’appartient pas à l’organisation active.');
    }

    /**
     * Sans fournisseur sur la tournée, le chauffeur est simplement vérifié dans
     * l'organisation ; avec fournisseur, il doit lui appartenir.
     */
    public function driver(string $driverId, ?string $providerId, string $organizationId): Driver
    {
        $driver = Driver::where('organization_id', $organizationId)->whereKey($driverId)->first();

        if ($driver === null) {
            $this->fail('driverId', 'Ce chauffeur n’appartient pas à l’organisation active.');
        }

        if ($providerId !== null && $driver->provider_id !== $providerId) {
            $this->fail('driverId', 'Ce chauffeur n’appartient pas au fournisseur de la tournée.');
        }

        return $driver;
    }

    public function vehicle(string $vehicleId, ?string $providerId, string $organizationId): Vehicle
    {
        $vehicle = Vehicle::inOrganization($organizationId)->whereKey($vehicleId)->first();

        if ($vehicle === null) {
            $this->fail('vehicleId', 'Ce véhicule n’appartient pas à l’organisation active.');
        }

        if ($providerId !== null && $vehicle->provider_id !== $providerId) {
            $this->fail('vehicleId', 'Ce véhicule n’appartient pas au fournisseur de la tournée.');
        }

        return $vehicle;
    }

    /**
     * Le service planifié doit venir d'une commande de la même organisation :
     * `order_services` n'a pas d'`organization_id`, le périmètre passe par la
     * commande.
     */
    public function orderService(string $orderServiceId, string $organizationId, string $field = 'orderServiceId'): OrderService
    {
        $service = OrderService::whereKey($orderServiceId)
            ->whereHas('order', fn ($order) => $order->where('organization_id', $organizationId))
            ->first();

        return $service ?? $this->fail($field, 'Ce service de commande n’est pas accessible dans l’organisation active.');
    }

    /**
     * Le colis affecté doit provenir de la commande du service concerné.
     *
     * Affecter le colis d'une autre commande produirait une tournée qui
     * transporte un colis que personne n'a demandé à ce service.
     */
    public function package(string $packageId, OrderService $orderService): Package
    {
        $package = Package::whereKey($packageId)->where('order_id', $orderService->order_id)->first();

        return $package ?? $this->fail('packageId', 'Ce colis n’appartient pas à la commande du service planifié.');
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}
