<?php

use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Tours\Models\Tour;

beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
});

// La colonne de la tournee signale un chargement qui depasse le vehicule :
// elle doit donc recevoir ses capacites avec la liste, sans ouvrir sa fiche.
it('lists each tour with the capacities of its vehicle', function (): void {
    $vehicle = Vehicle::factory()->forOrganization($this->organization)->create([
        'payload_capacity' => 500,
        'volume_capacity' => 50,
        'pallet_capacity' => 150,
    ]);
    Tour::factory()->forOrganization($this->organization)->create(['vehicle_id' => $vehicle->id]);

    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->getJson('/api/v1/tours')
        ->assertOk()
        ->assertJsonPath('data.0.vehicleCapacity', [
            'weight' => '500.000',
            'volume' => '50.0000',
            'pallets' => 150,
        ]);
});

it('gives no capacity to a tour without a vehicle', function (): void {
    Tour::factory()->forOrganization($this->organization)->create();

    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->getJson('/api/v1/tours')
        ->assertOk()
        ->assertJsonPath('data.0.vehicleCapacity', null);
});
