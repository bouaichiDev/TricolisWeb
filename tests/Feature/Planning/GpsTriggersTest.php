<?php

use App\Modules\Addresses\Models\Address;
use App\Modules\Addresses\Models\EntityAddress;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Planning\Jobs\GeocodeAddressJob;
use App\Modules\Planning\Jobs\RecalculateTourRouteJob;
use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Models\TourStop;
use Illuminate\Support\Facades\Queue;

/**
 * Qui déclenche le géocodage et le calcul d'itinéraire.
 *
 * Les deux services distants existaient sans appelant : ces tests fixent les
 * moments où ils partent, et surtout ceux où ils ne partent pas — le quota est
 * limité, et un appel par frappe le viderait.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->agency = Agency::factory()->create(['organization_id' => $this->organization->id]);

    Queue::fake();

    // Une adresse n'est visible que par sa liaison : `addresses` ne porte pas
    // d'organisation, ce qui est voulu — l'adresse est partagee, pas la liaison.
    $this->linked = function (array $attributes = []): Address {
        $address = Address::factory()->create($attributes);

        EntityAddress::create([
            'organization_id' => $this->organization->id,
            'address_id' => $address->id,
            'entity_type' => 'organization',
            'entity_id' => $this->organization->id,
        ]);

        return $address;
    };

    $this->payload = fn (array $o = []): array => array_merge([
        'name' => 'Entrepôt sud',
        'addressLine1' => '12 rue des Oliviers',
        'postalCode' => '20000',
        'city' => 'Casablanca',
        'country' => 'MA',
        'status' => 'active',
    ], $o);
});

describe('géocodage', function (): void {
    it('met en file une adresse créée sans point', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/addresses', ($this->payload)())->assertCreated();

        Queue::assertPushed(GeocodeAddressJob::class, 1);
    });

    /** Des coordonnées fournies font foi : rien à demander au service. */
    it('ne demande rien quand les coordonnées sont données', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/addresses', ($this->payload)([
                'latitude' => 33.59,
                'longitude' => -7.62,
            ]))->assertCreated();

        Queue::assertNotPushed(GeocodeAddressJob::class);
    });

    it('redemande un point quand l’adresse a changé de place', function (): void {
        $address = ($this->linked)(['latitude' => 33.59, 'longitude' => -7.62]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/addresses/{$address->id}", ['city' => 'Rabat'])->assertOk();

        Queue::assertPushed(GeocodeAddressJob::class, 1);
    });

    /**
     * Renommer « Entrepôt nord » en « Dépôt 2 » ne déplace pas les murs :
     * redemander un géocodage à chaque retouche de libellé viderait le quota.
     */
    it('ne redemande rien pour un simple renommage', function (): void {
        $address = ($this->linked)(['latitude' => 33.59, 'longitude' => -7.62]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/addresses/{$address->id}", ['name' => 'Dépôt 2'])->assertOk();

        Queue::assertNotPushed(GeocodeAddressJob::class);
    });

    /** Une correction manuelle ne doit pas être écrasée par le service. */
    it('respecte des coordonnées corrigées à la main', function (): void {
        $address = ($this->linked)(['latitude' => null, 'longitude' => null]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/addresses/{$address->id}", [
                'city' => 'Rabat',
                'latitude' => 34.02,
                'longitude' => -6.83,
            ])->assertOk();

        Queue::assertNotPushed(GeocodeAddressJob::class);
    });
});

describe('itinéraire', function (): void {
    it('recalcule après un réordonnancement des arrêts', function (): void {
        $tour = Tour::factory()->forAgency($this->agency)->create(['status' => 'draft']);

        $stops = collect([1, 2])->map(fn (int $sequence) => TourStop::factory()->create([
            'tour_id' => $tour->id,
            'address_id' => Address::factory()->create()->id,
            'sequence' => $sequence,
            'status' => 'pending',
        ]));

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/tours/{$tour->id}/stops/reorder", [
                'ids' => $stops->pluck('id')->reverse()->values()->all(),
            ])->assertNoContent();

        Queue::assertPushed(RecalculateTourRouteJob::class);
    });

    /**
     * **Sans processus de file d'attente.** Un temps de route qui n'arrive que
     * si quelqu'un pense à lancer un worker n'arrive pas : le planificateur
     * voit ses arrêts s'aligner sans rien apprendre du chemin, et rien ne lui
     * dit qu'un calcul attend. Le Job vise `sync` pour cela.
     */
    it('calcule pendant la requête, sans processus de file', function (): void {
        expect((new RecalculateTourRouteJob('01JQZ0000000000000TOUR9'))->connection)->toBe('sync');
    });
});
