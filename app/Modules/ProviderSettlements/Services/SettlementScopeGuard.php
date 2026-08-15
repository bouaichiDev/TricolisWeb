<?php

declare(strict_types=1);

namespace App\Modules\ProviderSettlements\Services;

use App\Modules\Orders\Models\OrderService;
use App\Modules\Providers\Models\Provider;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Vérifie les références d'un décompte fournisseur.
 *
 * Le §18 demande que l'`OrderService` soit « cohérent avec le Provider du
 * settlement **lorsque cette relation existe via Tour/affectation** ». Elle
 * existe depuis la Phase 4 : un service est planifié sur un arrêt, l'arrêt
 * appartient à une tournée, la tournée porte un fournisseur.
 *
 * Le contrôle est donc conditionnel, et c'est délibéré : un service jamais
 * planifié, ou planifié sur une tournée sans fournisseur affecté, reste
 * décomptable — sinon il serait impossible de payer une prestation sous-traitée
 * hors tournée. Mais dès qu'une tournée a désigné un fournisseur, décompter ce
 * service à un autre est refusé.
 */
final readonly class SettlementScopeGuard
{
    public function provider(string $providerId, string $organizationId): Provider
    {
        $provider = Provider::where('organization_id', $organizationId)->whereKey($providerId)->first();

        return $provider ?? $this->fail('providerId', 'Ce fournisseur n’appartient pas à l’organisation active.');
    }

    /**
     * Le service doit relever de l'organisation, et — s'il est planifié chez un
     * fournisseur — de celui du décompte.
     */
    public function orderService(string $orderServiceId, Provider $provider, string $field = 'orderServiceId'): OrderService
    {
        $service = OrderService::whereKey($orderServiceId)
            ->whereHas('order', fn ($order) => $order->where('organization_id', $provider->organization_id))
            ->first();

        if ($service === null) {
            $this->fail($field, 'Ce service n’est pas accessible dans l’organisation active.');
        }

        $this->assertPlannedProviderMatches($service, $provider, $field);

        return $service;
    }

    /**
     * Fournisseurs ayant réellement planifié ce service, via ses affectations.
     */
    private function assertPlannedProviderMatches(OrderService $service, Provider $provider, string $field): void
    {
        $plannedProviderIds = DB::table('tour_stop_services')
            ->join('tour_stops', 'tour_stops.id', '=', 'tour_stop_services.tour_stop_id')
            ->join('tours', 'tours.id', '=', 'tour_stops.tour_id')
            ->where('tour_stop_services.order_service_id', $service->id)
            ->where('tour_stop_services.is_active_assignment', true)
            ->whereNotNull('tours.provider_id')
            ->distinct()
            ->pluck('tours.provider_id')
            ->all();

        if ($plannedProviderIds === []) {
            return;
        }

        if (! in_array($provider->id, $plannedProviderIds, true)) {
            $this->fail($field, 'Ce service a été planifié chez un autre fournisseur.');
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}
