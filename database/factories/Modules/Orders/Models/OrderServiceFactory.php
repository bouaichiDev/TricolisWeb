<?php

namespace Database\Factories\Modules\Orders\Models;

use App\Modules\Addresses\Models\Address;
use App\Modules\Orders\Enums\OrderServiceStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;
use App\Modules\Orders\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderService>
 */
class OrderServiceFactory extends Factory
{
    public function modelName(): string
    {
        return OrderService::class;
    }

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'service_id' => Service::factory(),
            'address_id' => Address::factory(),
            'service_number' => fake()->unique()->bothify('SRV-#####'),
            'sequence' => fake()->unique()->numberBetween(1, 9999),
            'requested_date' => now()->toDateString(),
            'quantity' => 1,
            'unit' => 'delivery',
            'required_time_minutes' => 30,
            'remaining_time_minutes' => 30,
            'weight' => 0,
            'volume' => 0,
            'package_count' => 0,
            'customer_unit_price' => 0,
            'customer_total_price' => 0,
            'provider_unit_cost' => 0,
            'provider_total_cost' => 0,
            'status' => OrderServiceStatus::DRAFT,
        ];
    }

    public function forOrder(Order $order): static
    {
        return $this->state(fn (): array => ['order_id' => $order->id]);
    }
}
