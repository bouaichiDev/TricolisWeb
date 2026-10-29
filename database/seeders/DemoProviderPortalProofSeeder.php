<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Addresses\Models\Address;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Documents\Models\Document;
use App\Modules\ProofOfDelivery\Models\ProofOfDelivery;
use App\Modules\Providers\Models\Provider;
use App\Modules\Tours\Enums\TourStatus;
use App\Modules\Tours\Enums\TourStopStatus;
use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Models\TourStop;
use App\Modules\Tours\Models\TourStopService;
use App\Shared\Database\MorphMap;
use Database\Seeders\Support\ProviderDemoOrder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Deux tournées d'Alpha portant chacune un service acquis (affecté, confirmé)
 * de `ProviderDemoOrder` — une commande passée par le service métier, jamais
 * une commande suivie par les démonstrations du portail client :
 *
 * - DEMO-ALPHA-3, déjà en cours, arrêt atteint : saisir une preuve ;
 * - DEMO-ALPHA-4, confirmée, sans chauffeur ni véhicule : le parcours complet
 *   (affecter, démarrer, arriver, prouver, terminer).
 *
 * Remises à zéro à chaque passage (statuts, ressources, heures, preuves et
 * fichiers de leurs arrêts) : les parcours se rejouent sans base neuve. Local
 * et test seulement. Appelé par `DemoProviderPortalTourSeeder`.
 */
class DemoProviderPortalProofSeeder extends Seeder
{
    public const string TOUR = 'DEMO-ALPHA-3';

    public const string JOURNEY_TOUR = 'DEMO-ALPHA-4';

    public function run(ProviderDemoOrder $order): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        $alpha = Provider::where('code', 'transports-alpha')->first();
        $agency = $alpha === null ? null : Agency::where('organization_id', $alpha->organization_id)->first();
        $address = Address::query()->first();

        if ($alpha === null || $agency === null || $address === null) {
            return;
        }

        $date = now()->addDay()->toDateString();
        $base = ['agency_id' => $agency->id, 'provider_id' => $alpha->id, 'tour_date' => $date, 'actual_end_at' => null, 'locked_by' => null];

        $running = $this->tour($alpha, self::TOUR, [...$base, 'planned_start_at' => "{$date} 18:00:00", 'planned_end_at' => "{$date} 20:00:00",
            'status' => TourStatus::IN_PROGRESS, 'actual_start_at' => "{$date} 18:05:00"]);
        $this->stop($running, $address->id, "{$date} 18:30:00", TourStopStatus::ARRIVED, "{$date} 18:32:00", $order->service($alpha->organization, 1)?->id);

        $journey = $this->tour($alpha, self::JOURNEY_TOUR, [...$base, 'planned_start_at' => "{$date} 20:30:00", 'planned_end_at' => "{$date} 22:00:00",
            'status' => TourStatus::CONFIRMED, 'actual_start_at' => null, 'driver_id' => null, 'vehicle_id' => null]);
        $this->stop($journey, $address->id, "{$date} 21:00:00", TourStopStatus::PENDING, null, $order->service($alpha->organization, 2)?->id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function tour(Provider $alpha, string $number, array $attributes): Tour
    {
        return Tour::updateOrCreate(['organization_id' => $alpha->organization_id, 'tour_number' => $number], $attributes);
    }

    private function stop(Tour $tour, string $addressId, string $planned, TourStopStatus $status, ?string $arrival, ?string $serviceId): void
    {
        $stop = TourStop::updateOrCreate(
            ['tour_id' => $tour->id, 'sequence' => 1],
            ['address_id' => $addressId, 'planned_arrival_at' => $planned, 'status' => $status, 'actual_arrival_at' => $arrival, 'actual_departure_at' => null],
        );

        $this->resetProofs($stop);

        if ($serviceId !== null && TourStopService::where('tour_stop_id', $stop->id)->doesntExist()) {
            TourStopService::create([
                'tour_stop_id' => $stop->id, 'order_service_id' => $serviceId, 'sequence_within_stop' => 1,
                'is_active_assignment' => true, 'confirmed_at' => now(), 'status' => 'planned',
            ]);
        }
    }

    /**
     * Les preuves d'un arrêt de démo, et les signatures et photos qu'elles ont
     * laissées (documents rattachés à cet arrêt, fichiers compris). Rien d'autre.
     */
    private function resetProofs(TourStop $stop): void
    {
        ProofOfDelivery::where('tour_stop_id', $stop->id)->delete();

        $documents = Document::withTrashed()
            ->whereHas('links', static fn ($link) => $link->where('entity_type', MorphMap::TOUR_STOP)->where('entity_id', $stop->id))
            ->get();

        Storage::disk('local')->delete($documents->pluck('storage_path')->filter()->all());
        $documents->each->forceDelete();
    }
}
