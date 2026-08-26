<?php

use App\Modules\Addresses\Models\Address;
use App\Modules\Addresses\Models\EntityAddress;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\Depot;
use App\Modules\Integrations\Models\OrganizationApiConfiguration;
use App\Modules\Orders\Models\Service;
use App\Modules\Planning\Services\DepotLocation;
use App\Modules\Planning\Services\GeocodingService;
use App\Modules\Planning\Services\LoadingServices;
use App\Shared\Database\MorphMap;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->seed();
    $this->organization = authOrganization();
    $this->loading = app(LoadingServices::class);
    $this->depots = app(DepotLocation::class);

    $this->setCodes = function (array $codes): void {
        $this->organization->forceFill([
            'settings' => ['planning' => ['loadingServiceCodes' => $codes]],
        ])->save();
        $this->organization->refresh();
    };
});

/**
 * Les services de chargement se reconnaissent à leur code, réglé par
 * organisation : deux transporteurs ne nomment pas leur chargement pareil.
 */
describe('services de chargement', function (): void {
    it('recognises a service by its configured code, whatever the case', function (): void {
        ($this->setCodes)(['load']);

        $loading = Service::factory()->create([
            'organization_id' => $this->organization->id, 'code' => 'LOAD',
        ]);
        $delivery = Service::factory()->create([
            'organization_id' => $this->organization->id, 'code' => 'DELIV',
        ]);

        expect($this->loading->isLoading($loading, $this->organization))->toBeTrue();
        expect($this->loading->isLoading($delivery, $this->organization))->toBeFalse();
        expect($this->loading->serviceIds($this->organization))->toBe([$loading->id]);
    });

    it('recognises nothing when no code is configured', function (): void {
        $service = Service::factory()->create([
            'organization_id' => $this->organization->id, 'code' => 'LOAD',
        ]);

        expect($this->loading->codes($this->organization))->toBe([]);
        expect($this->loading->isLoading($service, $this->organization))->toBeFalse();
        expect($this->loading->serviceIds($this->organization))->toBe([]);
    });

    /**
     * Le garde-fou de la reconnaissance par code : un code mal saisi, ou un
     * service renommé depuis, ferait cesser le regroupement au dépôt sans un
     * mot. Il se voit ici avant que la planification ne s'en aperçoive.
     */
    it('reports a configured code matching no service', function (): void {
        ($this->setCodes)(['LOAD', 'CHARG']);

        Service::factory()->create([
            'organization_id' => $this->organization->id, 'code' => 'LOAD',
        ]);

        expect($this->loading->unmatched($this->organization))->toBe(['CHARG']);
    });

    it('reports nothing when every code matches', function (): void {
        ($this->setCodes)(['LOAD']);

        Service::factory()->create([
            'organization_id' => $this->organization->id, 'code' => 'load',
        ]);

        expect($this->loading->unmatched($this->organization))->toBe([]);
    });
});

/**
 * Le dépôt reçoit son adresse par les liaisons polymorphes, comme un client :
 * le mécanisme accepte déjà `depot`, il suffit de l'employer.
 */
describe('adresse du dépôt', function (): void {
    beforeEach(function (): void {
        $agency = Agency::factory()->create(['organization_id' => $this->organization->id]);
        $this->depot = Depot::factory()->create(['agency_id' => $agency->id]);
    });

    it('takes the default link over the others', function (): void {
        $other = Address::factory()->create(['city' => 'Bureau']);
        $main = Address::factory()->create(['city' => 'Quai']);

        foreach ([[$other, false], [$main, true]] as [$address, $isDefault]) {
            EntityAddress::create([
                'organization_id' => $this->organization->id,
                'address_id' => $address->id,
                'entity_type' => MorphMap::DEPOT,
                'entity_id' => $this->depot->id,
                'is_default' => $isDefault,
            ]);
        }

        expect($this->depots->addressOf($this->depot)?->id)->toBe($main->id);
    });

    /** Sans adresse, on le dit : partir du siège serait faux sans le montrer. */
    it('gives nothing for a depot without any address', function (): void {
        expect($this->depots->addressOf($this->depot))->toBeNull();
        expect($this->depots->coordinatesOf($this->depot, $this->organization->id))->toBeNull();
    });

    it('geocodes the departure address when it has no coordinates', function (): void {
        OrganizationApiConfiguration::create([
            'organization_id' => $this->organization->id,
            'code' => GeocodingService::CONFIGURATION_CODE,
            'name' => 'Géocodage',
            'base_url' => 'https://gps.example.test',
            'auth_type' => 'none',
            'settings' => ['path' => '/getLocation', 'queryKey' => 'adress'],
            'timeout_seconds' => 10,
            'is_active' => true,
        ]);

        Http::fake(['*' => Http::response('<Result><Lat>31.6295</Lat><Lng>-7.9811</Lng></Result>')]);

        $address = Address::factory()->create(['latitude' => null, 'longitude' => null, 'city' => 'Marrakech']);
        EntityAddress::create([
            'organization_id' => $this->organization->id,
            'address_id' => $address->id,
            'entity_type' => MorphMap::DEPOT,
            'entity_id' => $this->depot->id,
            'is_default' => true,
        ]);

        $coordinates = $this->depots->coordinatesOf($this->depot, $this->organization->id);

        expect($coordinates)->toBe([31.6295, -7.9811]);
        // Les coordonnees sont retenues : la prochaine tournee ne redemandera pas.
        expect((float) $address->fresh()->latitude)->toBe(31.6295);
    });
});
