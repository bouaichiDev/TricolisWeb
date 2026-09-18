<?php

use App\Modules\Claims\Models\Claim;
use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;

beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
});

// L'historique d'une reclamation nomme ce qu'elle vise au lieu d'en montrer
// l'identifiant : le detail doit donc porter le client, la commande et le
// numero du service.
it('names what the claim points to', function (): void {
    $order = Order::factory()->forOrganization($this->organization)->create(['customer_id' => $this->customer->id]);
    $service = OrderService::factory()->create(['order_id' => $order->id]);
    $claim = Claim::factory()->forOrder($order)->create(['order_service_id' => $service->id]);

    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->getJson("/api/v1/claims/{$claim->id}")
        ->assertOk()
        ->assertJsonPath('data.customer.name', $this->customer->name)
        ->assertJsonPath('data.order.orderNumber', $order->order_number)
        ->assertJsonPath('data.orderService', [
            'id' => $service->id,
            'serviceNumber' => $service->service_number,
        ]);
});

it('gives no service to a claim on the whole order', function (): void {
    $claim = Claim::factory()->forCustomer($this->customer)->create();

    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->getJson("/api/v1/claims/{$claim->id}")
        ->assertOk()
        ->assertJsonPath('data.orderService', null);
});
