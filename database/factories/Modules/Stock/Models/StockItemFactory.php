<?php

namespace Database\Factories\Modules\Stock\Models;

use App\Modules\Customers\Models\Customer;
use App\Modules\Stock\Models\StockItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockItem>
 */
class StockItemFactory extends Factory
{
    public function modelName(): string
    {
        return StockItem::class;
    }

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'catalog_item_id' => null,
            'article_code' => fake()->unique()->bothify('ART-#####'),
            // Nul par defaut : l'unicite (customer_id, barcode) rendrait les
            // jeux de test fragiles si chaque article en portait un.
            'barcode' => null,
            'description' => fake()->sentence(3),
            'status' => 'active',
        ];
    }

    public function forCustomer(Customer $customer): static
    {
        return $this->state(fn (): array => ['customer_id' => $customer->id]);
    }
}
