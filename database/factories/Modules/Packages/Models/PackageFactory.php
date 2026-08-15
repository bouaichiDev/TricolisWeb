<?php

namespace Database\Factories\Modules\Packages\Models;

use App\Modules\Orders\Models\Order;
use App\Modules\Packages\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Package>
 */
class PackageFactory extends Factory
{
    public function modelName(): string
    {
        return Package::class;
    }

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'barcode' => fake()->unique()->ean13(),
            'reference' => fake()->optional()->bothify('REF-####'),
            'quantity' => 1,
            'weight' => fake()->randomFloat(3, 0.5, 100),
            'volume' => fake()->randomFloat(4, 0.01, 3),
            'status' => 'draft',
        ];
    }

    public function forOrder(Order $order): static
    {
        return $this->state(fn (): array => ['order_id' => $order->id]);
    }

    public function childOf(Package $parent): static
    {
        return $this->state(fn (): array => [
            'order_id' => $parent->order_id,
            'parent_package_id' => $parent->id,
        ]);
    }
}
