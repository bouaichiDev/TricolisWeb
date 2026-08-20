<?php

declare(strict_types=1);

use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;
use App\Modules\Orders\Models\OrderServicePackage;
use App\Modules\Packages\Models\Package;

/**
 * Colis pris en charge par un service.
 *
 * La relation `OrderServicePackage` existe au diagramme et se crée à la
 * création complète d'une commande ; aucune route ne permettait de l'ajouter
 * ni de la retirer ensuite.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];

    $this->order = Order::factory()->forOrganization($this->organization)
        ->create(['created_by' => $this->user->id]);

    $this->service = OrderService::factory()->forOrder($this->order)->create();
    $this->package = Package::factory()->forOrder($this->order)->create(['reference' => 'PAL-1']);
});

function linkPath(string $order, string $service, string $link = ''): string
{
    $path = "/api/v1/orders/{$order}/services/{$service}/packages";

    return $link === '' ? $path : "{$path}/{$link}";
}

it('rattache un colis à un service', function (): void {
    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->postJson(linkPath($this->order->id, $this->service->id), [
            'packageId' => $this->package->id,
            'quantity' => 2,
            'handlingInstructions' => 'Ne pas gerber',
        ])
        ->assertCreated()
        ->assertJsonPath('data.packageId', $this->package->id)
        ->assertJsonPath('data.handlingInstructions', 'Ne pas gerber');

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'created',
        'entity_type' => 'order_service_package',
    ]);
});

it('liste les colis pris en charge, avec leur colis chargé', function (): void {
    OrderServicePackage::create([
        'order_service_id' => $this->service->id,
        'package_id' => $this->package->id,
        'quantity' => 1,
        'status' => 'pending',
    ]);

    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->getJson(linkPath($this->order->id, $this->service->id))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.package.reference', 'PAL-1');
});

/** Deux liaisons rendraient la quantité prise en charge indéterminée. */
it('refuse de rattacher deux fois le même colis', function (): void {
    OrderServicePackage::create([
        'order_service_id' => $this->service->id,
        'package_id' => $this->package->id,
        'quantity' => 1,
        'status' => 'pending',
    ]);

    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->postJson(linkPath($this->order->id, $this->service->id), ['packageId' => $this->package->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('packageId');
});

it('refuse un colis d’une autre commande', function (): void {
    $other = Order::factory()->forOrganization($this->organization)->create(['created_by' => $this->user->id]);
    $foreign = Package::factory()->forOrder($other)->create();

    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->postJson(linkPath($this->order->id, $this->service->id), ['packageId' => $foreign->id])
        ->assertNotFound();
});

it('modifie la quantité prise en charge', function (): void {
    $link = OrderServicePackage::create([
        'order_service_id' => $this->service->id,
        'package_id' => $this->package->id,
        'quantity' => 1,
        'status' => 'pending',
    ]);

    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->patchJson(linkPath($this->order->id, $this->service->id, $link->id), ['quantity' => 4])
        ->assertOk()
        ->assertJsonPath('data.quantity', '4.000');
});

/** Retirer la prise en charge ne supprime pas le colis. */
it('retire la prise en charge sans supprimer le colis', function (): void {
    $link = OrderServicePackage::create([
        'order_service_id' => $this->service->id,
        'package_id' => $this->package->id,
        'quantity' => 1,
        'status' => 'pending',
    ]);

    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->deleteJson(linkPath($this->order->id, $this->service->id, $link->id))
        ->assertNoContent();

    $this->assertDatabaseMissing('order_service_packages', ['id' => $link->id]);
    $this->assertDatabaseHas('packages', ['id' => $this->package->id]);
});

it('refuse une prise en charge appartenant à un autre service', function (): void {
    $otherService = OrderService::factory()->forOrder($this->order)->create();
    $link = OrderServicePackage::create([
        'order_service_id' => $otherService->id,
        'package_id' => $this->package->id,
        'quantity' => 1,
        'status' => 'pending',
    ]);

    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->deleteJson(linkPath($this->order->id, $this->service->id, $link->id))
        ->assertNotFound();
});

/** Au-delà des statuts ouverts, le contenu de la commande est engagé. */
it('refuse toute modification quand la commande est figée', function (): void {
    $frozen = Order::factory()->forOrganization($this->organization)
        ->withStatus(OrderStatus::PLANNED)->create(['created_by' => $this->user->id]);
    $service = OrderService::factory()->forOrder($frozen)->create();
    $package = Package::factory()->forOrder($frozen)->create();

    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->postJson(linkPath($frozen->id, $service->id), ['packageId' => $package->id])
        ->assertStatus(409);
});
