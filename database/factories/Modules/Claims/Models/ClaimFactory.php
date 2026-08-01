<?php

namespace Database\Factories\Modules\Claims\Models;

use App\Modules\Claims\Models\Claim;
use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Models\Order;
use App\Modules\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Claim>
 */
class ClaimFactory extends Factory
{
    public function modelName(): string
    {
        return Claim::class;
    }

    public function definition(): array
    {
        $organization = Organization::factory();

        return [
            'organization_id' => $organization,
            // Le client doit relever de la meme organisation que la reclamation.
            'customer_id' => fn (array $attributes): Customer => Customer::factory()->create([
                'organization_id' => $attributes['organization_id'],
            ]),
            'order_id' => null,
            'order_service_id' => null,
            'tour_id' => null,
            'title' => fake()->sentence(4),
            'description' => null,
            'claim_type' => 'damage',
            'cause' => null,
            'decision' => null,
            'follow_up' => null,
            'result' => null,
            'cost' => null,
            'status' => 'open',
            'created_by' => null,
            'responsible_user_id' => null,
            'created_at' => now(),
            // Une reclamation nait ouverte : cloturee, elle ne serait plus
            // supprimable, ce qui fausserait les jeux de test par defaut.
            'closed_at' => null,
        ];
    }

    public function forCustomer(Customer $customer): static
    {
        return $this->state(fn (): array => [
            'organization_id' => $customer->organization_id,
            'customer_id' => $customer->id,
        ]);
    }

    public function forOrder(Order $order): static
    {
        return $this->state(fn (): array => [
            'organization_id' => $order->organization_id,
            'customer_id' => $order->customer_id,
            'order_id' => $order->id,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'closed_at' => $attributes['created_at'],
        ]);
    }

    public function withCost(float $cost): static
    {
        return $this->state(fn (): array => ['cost' => $cost]);
    }
}
