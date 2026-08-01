<?php

namespace Database\Factories\Modules\Orders\Models;

use App\Modules\Agencies\Models\Agency;
use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function modelName(): string
    {
        return Order::class;
    }

    public function definition(): array
    {
        $organization = Organization::factory();

        return [
            'organization_id' => $organization,
            'customer_id' => Customer::factory(),
            'agency_id' => Agency::factory(),
            'order_number' => fake()->unique()->bothify('ORD-2026-######'),
            'order_date' => now(),
            'source' => OrderSource::INTERNAL,
            'currency_code' => 'MAD',
            'status' => OrderStatus::DRAFT,
            'created_by' => User::factory(),
        ];
    }

    /**
     * Rattache la commande à une organisation, avec un client et une agence
     * cohérents : une commande dont le client appartient à une autre
     * organisation serait un jeu de données invalide.
     */
    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn (): array => [
            'organization_id' => $organization->id,
            'customer_id' => Customer::factory()->create(['organization_id' => $organization->id])->id,
            'agency_id' => Agency::factory()->create(['organization_id' => $organization->id])->id,
        ]);
    }

    public function withStatus(OrderStatus $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }
}
