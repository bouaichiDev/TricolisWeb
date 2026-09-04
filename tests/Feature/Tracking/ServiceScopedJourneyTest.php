<?php

use App\Modules\Addresses\Models\Address;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;
use App\Modules\Orders\Models\Service;
use App\Modules\Tracking\Models\TrackingEvent;
use App\Modules\Tracking\Models\TrackingEventDefinition;
use App\Shared\Database\MorphMap;

/**
 * Le parcours, prestation par prestation.
 *
 * Une commande porte souvent un chargement, une livraison et un montage. Le
 * parcours les mélangeait toutes : le destinataire voyait « planifié » trois
 * fois sans savoir de quoi, alors qu'il ne suit que sa livraison.
 *
 * **Les événements naissent tout seuls.** `TracksStatusChanges` les publie dès
 * qu'un statut est posé ; ces tests posent donc des statuts et regardent ce qui
 * en sort, plutôt que d'appeler le publieur à la main — c'est le chemin réel.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->url = '/api/v1/tracking-event-definitions';

    $this->delivery = Service::factory()->create([
        'organization_id' => $this->organization->id,
        'code' => 'DELIVERY',
        'name' => 'Livraison',
    ]);

    $this->loading = Service::factory()->create([
        'organization_id' => $this->organization->id,
        'code' => 'LOAD',
        'name' => 'Chargement',
    ]);

    $this->order = Order::factory()->create(['organization_id' => $this->organization->id]);

    /**
     * Une prestation posée sur la commande, encore hors parcours.
     *
     * Elle naît `draft` : aucune étape ne décrit cet état, donc rien n'est
     * publié tant qu'on n'a pas fait avancer le statut soi-même.
     */
    $this->serviceOf = fn (Service $service): OrderService => OrderService::factory()->create([
        'order_id' => $this->order->id,
        'service_id' => $service->id,
        'address_id' => Address::factory()->create()->id,
        'status' => 'draft',
    ]);

    $this->planify = function (OrderService $service): void {
        $service->forceFill(['status' => 'planned'])->save();
    };

    $this->step = fn (array $attributes = []): TrackingEventDefinition => TrackingEventDefinition::create(array_merge([
        'organization_id' => $this->organization->id,
        'source_type' => MorphMap::ORDER_SERVICE,
        'status_code' => 'planned',
        'code' => 'Planned',
        'title' => 'Planifié',
        'position' => 10,
        'active' => true,
    ], $attributes));
});

describe('portée par prestation', function (): void {
    /**
     * Le chargement au dépôt intéresse le planificateur, jamais le
     * destinataire : une étape qui vise la livraison ne doit pas se déclencher
     * sur un chargement.
     */
    it('ne déclenche pas une étape de livraison sur un chargement', function (): void {
        ($this->step)(['service_id' => $this->delivery->id]);

        ($this->planify)(($this->serviceOf)($this->loading));

        expect(TrackingEvent::count())->toBe(0);
    });

    it('déclenche sur la prestation qu’elle vise', function (): void {
        ($this->step)(['service_id' => $this->delivery->id]);

        ($this->planify)(($this->serviceOf)($this->delivery));

        expect(TrackingEvent::count())->toBe(1);
    });

    /** Une étape sans prestation reste ce qu'elle était : bonne pour toutes. */
    it('garde les étapes générales pour toutes les prestations', function (): void {
        ($this->step)();

        ($this->planify)(($this->serviceOf)($this->loading));

        expect(TrackingEvent::count())->toBe(1);
    });

    /**
     * L'étape de la prestation l'emporte : une organisation qui a décrit le
     * parcours de sa livraison n'attend pas que l'étape générale s'y applique
     * aussi, avec un autre libellé.
     */
    it('préfère l’étape de la prestation à l’étape générale', function (): void {
        ($this->step)(['code' => 'Generale', 'title' => 'Étape générale']);
        ($this->step)([
            'service_id' => $this->delivery->id,
            'code' => 'Livraison',
            'title' => 'Livraison planifiée',
        ]);

        ($this->planify)(($this->serviceOf)($this->delivery));

        expect(TrackingEvent::first()->event_type)->toBe('Livraison');
    });

    /**
     * Le defaut constate : un « livre » publie par le chargement sous une
     * configuration plus ancienne empechait la livraison de produire le sien.
     * L'etape restait alors marquee du mauvais moment, a jamais.
     */
    it('laisse la prestation visée produire le sien malgré un homonyme', function (): void {
        ($this->step)(['service_id' => $this->delivery->id]);

        // L'evenement d'avant, publie par le chargement quand l'etape ne visait
        // encore aucune prestation.
        $chargement = ($this->serviceOf)($this->loading);

        TrackingEvent::create([
            'organization_id' => $this->organization->id,
            'order_id' => $this->order->id,
            'order_service_id' => $chargement->id,
            'event_type' => 'Planned',
            'status' => 'planned',
            'occurred_at' => now()->subHour(),
        ]);

        ($this->planify)(($this->serviceOf)($this->delivery));

        expect(TrackingEvent::where('event_type', 'Planned')->count())->toBe(2);
    });

    /** Deux prestations peuvent décrire le même statut, chacune à sa façon. */
    it('accepte le même statut sur deux prestations', function (): void {
        ($this->step)(['service_id' => $this->delivery->id, 'code' => 'LivrPlan']);
        ($this->step)(['service_id' => $this->loading->id, 'code' => 'ChargPlan']);

        ($this->planify)(($this->serviceOf)($this->delivery));
        ($this->planify)(($this->serviceOf)($this->loading));

        expect(TrackingEvent::pluck('event_type')->sort()->values()->all())
            ->toBe(['ChargPlan', 'LivrPlan']);
    });
});

describe('ce que le client voit', function (): void {
    it('règle la visibilité, la preuve et la prestation par l’API', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson($this->url, [
                'sourceType' => MorphMap::ORDER_SERVICE,
                'statusCode' => 'completed',
                'code' => 'Livree',
                'title' => 'Livré',
                'serviceId' => $this->delivery->id,
                'visibleToCustomer' => true,
                'showsProofOfDelivery' => true,
            ])->assertCreated();

        expect($response->json('data.serviceId'))->toBe($this->delivery->id)
            ->and($response->json('data.visibleToCustomer'))->toBeTrue()
            ->and($response->json('data.showsProofOfDelivery'))->toBeTrue();
    });

    /**
     * Coupées par défaut : une organisation qui n'a rien réglé ne doit pas
     * exposer au destinataire des étapes pensées pour l'exploitation.
     */
    it('ne montre rien au client tant que rien n’est dit', function (): void {
        // Relue depuis la base : les valeurs par defaut y sont posees, pas sur
        // l'objet tout juste cree.
        $step = ($this->step)()->fresh();

        expect($step->visible_to_customer)->toBeFalse()
            ->and($step->shows_proof_of_delivery)->toBeFalse();
    });

    it('nomme la prestation dans la liste', function (): void {
        ($this->step)(['service_id' => $this->delivery->id]);

        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson($this->url)->assertOk();

        expect($response->json('data.0.serviceName'))->toBe('Livraison');
    });
});
