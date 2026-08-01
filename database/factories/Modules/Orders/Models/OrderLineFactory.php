<?php

namespace Database\Factories\Modules\Orders\Models;

use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderLine>
 */
class OrderLineFactory extends Factory
{
    public function modelName(): string
    {
        return OrderLine::class;
    }

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'article_code' => fake()->bothify('ART-####'),
            'name' => fake()->words(3, true),
            'quantity' => fake()->numberBetween(1, 10),
            'weight' => fake()->randomFloat(3, 0.1, 20),
            'volume' => fake()->randomFloat(4, 0.001, 1),
            'status' => 'active',
        ];
    }

    public function forOrder(Order $order): static
    {
        return $this->state(fn (): array => ['order_id' => $order->id]);
    }
}
