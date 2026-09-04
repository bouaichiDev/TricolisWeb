<?php

use App\Modules\Addresses\Models\Address;
use App\Modules\Addresses\Models\EntityAddress;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\Depot;
use App\Modules\Contacts\Models\AddressContact;
use App\Modules\Contacts\Models\EntityContact;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderLine;
use App\Modules\Orders\Models\OrderService;
use App\Modules\Packages\Models\Package;
use Carbon\CarbonImmutable;
use Database\Seeders\Support\SwissCatalogue;
use Database\Seeders\Support\SwissCustomerBook;
use Database\Seeders\Support\SwissOrderFactory;
use Database\Seeders\Support\SwissServiceMix;

/**
 * Le jeu de démonstration, éprouvé sans le semer.
 *
 * `SwissOrderSeeder` ne s'exécute qu'en `local` — neuf cents commandes
 * fausseraient toute assertion qui compte ou pagine. Ce sont donc ses pièces
 * qu'on éprouve ici, sur quelques commandes : c'est là que vivent les règles.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();

    $agency = Agency::factory()->create(['organization_id' => $this->organization->id]);
    $depot = Depot::factory()->create(['agency_id' => $agency->id]);
    $depotAddress = Address::factory()->create();

    EntityAddress::create([
        'organization_id' => $this->organization->id,
        'address_id' => $depotAddress->id,
        'entity_type' => 'depot',
        'entity_id' => $depot->id,
        'address_type' => 'operations',
        'is_default' => true,
    ]);

    $this->depotAddressId = $depotAddress->id;
    $catalogue = app(SwissCatalogue::class);
    $catalogue->loadingServiceId($this->organization);

    $this->customers = $catalogue->customers($this->organization);

    $this->factory = new SwissOrderFactory(
        $this->organization->id,
        $agency->id,
        $depot->id,
        new SwissServiceMix(
            $catalogue->services($this->organization),
            $catalogue->serviceMinutes(),
            $depotAddress->id,
        ),
        $this->user->id,
    );

    $this->make = fn (int $index): Order => $this->factory->create(
        sprintf('CMD-%04d', $index + 1),
        CarbonImmutable::parse('2026-10-05'),
        $index,
        $this->customers[$index % count($this->customers)],
    );
});

describe('le carnet clients', function (): void {
    it('crée cinq clients, et pas un par localité', function (): void {
        expect($this->customers)->toHaveCount(5);
    });

    /**
     * Le défaut corrigé : une adresse neuve par commande donnait au client
     * autant de rues que de commandes, et rendait son onglet illisible.
     */
    it('donne trois adresses à chaque client, une par rôle', function (): void {
        foreach ($this->customers as $customer) {
            $types = EntityAddress::where('entity_type', 'customer')
                ->where('entity_id', $customer->id)
                ->pluck('address_type')
                ->sort()
                ->values()
                ->all();

            expect($types)->toBe(['billing', 'delivery', 'load']);
        }
    });

    it('rattache un contact à chaque adresse', function (): void {
        foreach ($this->customers as $customer) {
            foreach (SwissCustomerBook::ROLES as $role) {
                expect(AddressContact::where('address_id', $customer->addressFor($role))->count())->toBe(1);
            }

            expect(EntityContact::where('entity_type', 'customer')
                ->where('entity_id', $customer->id)->count())->toBe(3);
        }
    });

    /** Deux semis appellent le carnet ; le second ne doit rien empiler. */
    it('ne recrée rien au second passage', function (): void {
        app(SwissCatalogue::class)->customers($this->organization);

        expect(EntityAddress::where('entity_type', 'customer')->count())->toBe(15);
    });
});

describe('les commandes', function (): void {
    /** Le client réclame sa commande par son propre code : il ouvre la référence. */
    it('préfixe la référence du code client', function (): void {
        $order = ($this->make)(0);

        expect($order->customer_reference)->toStartWith($this->customers[0]->code.'-');
    });

    /**
     * Une commande qui porte toujours les mêmes deux prestations n'éprouve ni le
     * cumul des durées sur un arrêt, ni une facture à plusieurs lignes.
     */
    it('fait tourner une à quatre prestations', function (): void {
        $counts = [];

        for ($index = 0; $index < 4; $index++) {
            $counts[] = OrderService::where('order_id', ($this->make)($index)->id)->count();
        }

        expect($counts)->toBe([1, 2, 3, 4]);
    });

    it('pose le chargement au dépôt et la livraison chez le client', function (): void {
        // Rang 1 : chargement puis livraison.
        $services = OrderService::where('order_id', ($this->make)(1)->id)
            ->orderBy('sequence')->pluck('address_id')->all();

        expect($services)->toBe([
            $this->depotAddressId,
            $this->customers[1]->addressFor('delivery'),
        ]);
    });

    it('ne prévient qu’une fois, sur la livraison', function (): void {
        $order = ($this->make)(3);

        $withContact = OrderService::where('order_id', $order->id)
            ->get()
            ->filter(fn (OrderService $service): bool => $service->contacts()->count() > 0);

        expect($withContact)->toHaveCount(1)
            ->and($withContact->first()->sequence)->toBe(2);
    });

    it('porte ses articles et ses colis', function (): void {
        $order = ($this->make)(0);

        expect(OrderLine::where('order_id', $order->id)->count())->toBeGreaterThan(0)
            ->and(Package::where('order_id', $order->id)->count())->toBe($order->package_count);
    });

    /**
     * Des commandes de poids identique ne rempliraient jamais un camion
     * autrement que par multiples : aucune contrainte de charge ne s'y
     * déclencherait.
     */
    it('varie le poids d’une commande à l’autre', function (): void {
        $weights = [];

        for ($index = 0; $index < 3; $index++) {
            $weights[] = (float) ($this->make)($index)->weight;
        }

        expect(array_unique($weights))->toHaveCount(3);
    });

    /** Le total annoncé doit être celui des colis, sans quoi une tournée ment. */
    it('annonce le poids réel de ses colis', function (): void {
        $order = ($this->make)(2);

        expect(round((float) $order->weight, 2))
            ->toBe(round((float) Package::where('order_id', $order->id)->sum('weight'), 2));
    });
});
