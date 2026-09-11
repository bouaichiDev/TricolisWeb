<?php

declare(strict_types=1);

use App\Modules\Addresses\Models\Address;
use App\Modules\Addresses\Models\EntityAddress;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Customers\Models\Customer;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\Service;
use App\Modules\Statuses\Models\Status;
use App\Shared\Database\MorphMap;

/**
 * Le statut choisi est bien celui que reçoit une entité qui naît.
 *
 * Fourni à la création, un statut est gardé ; absent, c'est le choix de
 * l'administrateur qui s'applique ; sans choix, la valeur de la colonne.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];

    $this->choose = function (string $source, string $code): void {
        $status = Status::where('source', $source)->where('code', $code)->first()
            ?? Status::factory()->forSource($source)->create(['code' => $code]);
        Status::where('source', $source)->update(['is_default' => false]);
        $status->update(['is_default' => true]);
    };

    $address = Address::factory()->create();
    EntityAddress::create([
        'organization_id' => $this->organization->id, 'address_id' => $address->id,
        'entity_type' => MorphMap::ORGANIZATION, 'entity_id' => $this->organization->id,
    ]);
    $service = Service::factory()->create(['organization_id' => $this->organization->id]);

    $this->createOrder = fn (array $overrides = []) => $this->actingAs($this->user, 'sanctum')
        ->withHeaders($this->headers)
        ->postJson('/api/v1/orders', [
            'customerId' => Customer::where('organization_id', $this->organization->id)->firstOrFail()->id,
            'agencyId' => Agency::where('organization_id', $this->organization->id)->firstOrFail()->id,
            'orderDate' => now()->toISOString(),
            'lines' => [['name' => 'Canapé', 'quantity' => 1]],
            'packages' => [['key' => 'P1', 'reference' => 'COL-1']],
            'services' => [array_merge([
                'serviceId' => $service->id, 'addressId' => $address->id, 'serviceNumber' => 'SRV-1',
                'sequence' => 1, 'requestedDate' => now()->toDateString(), 'quantity' => 1, 'unit' => 'U',
                'requiredTimeMinutes' => 30, 'remainingTimeMinutes' => 30, 'weight' => 0, 'volume' => 0,
                'packageCount' => 1, 'customerUnitPrice' => 0, 'customerTotalPrice' => 0,
                'providerUnitCost' => 0, 'providerTotalCost' => 0,
                'packages' => [['packageKey' => 'P1']],
            ], $overrides)],
        ]);
});

it('donne à la commande, ses lignes, ses colis et ses services le statut choisi', function (): void {
    ($this->choose)(MorphMap::ORDER, 'confirmed');
    ($this->choose)(MorphMap::ORDER_SERVICE, 'pending');
    ($this->choose)(MorphMap::ORDER_LINE, 'created');
    ($this->choose)(MorphMap::PACKAGE, 'received');
    ($this->choose)(MorphMap::ORDER_SERVICE_PACKAGE, 'waiting');

    $order = Order::findOrFail(($this->createOrder)()->assertCreated()->json('data.id'));
    $service = $order->orderServices()->firstOrFail();

    expect($order->status->value)->toBe('confirmed')
        ->and($service->status->value)->toBe('pending')
        ->and($order->lines()->firstOrFail()->status)->toBe('created')
        ->and($order->packages()->firstOrFail()->status)->toBe('received')
        ->and($service->servicePackages()->firstOrFail()->status)->toBe('waiting');
});

it('garde le statut fourni à la création', function (): void {
    ($this->choose)(MorphMap::ORDER_SERVICE, 'pending');

    $order = Order::findOrFail(($this->createOrder)(['status' => 'ready_to_plan'])->assertCreated()->json('data.id'));

    expect($order->orderServices()->firstOrFail()->status->value)->toBe('ready_to_plan');
});

/** Sans choix, rien ne change : la valeur de la colonne, comme avant. */
it('retombe sur la valeur de la colonne sans choix', function (): void {
    $order = Order::findOrFail(($this->createOrder)()->assertCreated()->json('data.id'));

    expect($order->status->value)->toBe('draft')
        ->and($order->lines()->firstOrFail()->status)->toBe('active')
        ->and($order->orderServices()->firstOrFail()->status->value)->toBe('draft');
});

/**
 * La prestation porte une énumération PHP : un code qu'elle ne connaît pas
 * ferait échouer la création entière. Mieux vaut la valeur de la colonne.
 */
it('écarte un choix que l’entité ne peut pas porter', function (): void {
    ($this->choose)(MorphMap::ORDER_SERVICE, 'statut_inconnu');

    $order = Order::findOrFail(($this->createOrder)()->assertCreated()->json('data.id'));

    expect($order->orderServices()->firstOrFail()->status->value)->toBe('draft');
});

/** Aucune entité n'est nommée : un module sans rapport en profite aussi. */
it('s’applique à toute entité qui porte un statut', function (): void {
    ($this->choose)(MorphMap::VEHICLE, 'maintenance');

    $vehicle = Vehicle::factory()->create(['organization_id' => $this->organization->id, 'status' => null]);

    expect($vehicle->fresh()->status)->toBe('maintenance');
});

describe('la réclamation', function (): void {
    beforeEach(function (): void {
        $this->claim = fn () => $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/claims', [
                'customerId' => Customer::factory()->create(['organization_id' => $this->organization->id])->id,
                'title' => 'Colis endommagé',
                'claimType' => 'damage',
            ]);
    });

    it('naît avec le statut choisi quand le formulaire n’en donne pas', function (): void {
        ($this->choose)(MorphMap::CLAIM, 'created');

        ($this->claim)()->assertCreated()->assertJsonPath('data.status', 'created');
    });

    /** La colonne n'a pas de valeur par défaut : sans choix, le statut reste exigé. */
    it('exige un statut quand rien ne le fournit', function (): void {
        ($this->claim)()->assertUnprocessable()->assertJsonValidationErrors('status');
    });
});
