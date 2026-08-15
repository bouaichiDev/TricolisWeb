<?php

namespace Database\Factories\Modules\Tours\Models;

use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;
use App\Modules\Tours\Models\TourStop;
use App\Modules\Tours\Models\TourStopService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TourStopService>
 */
class TourStopServiceFactory extends Factory
{
    public function modelName(): string
    {
        return TourStopService::class;
    }

    public function definition(): array
    {
        return [
            'tour_stop_id' => TourStop::factory(),
            'order_service_id' => OrderService::factory(),
            'sequence_within_stop' => fake()->unique()->numberBetween(1, 9999),
            'is_active_assignment' => true,
            'status' => 'planned',
        ];
    }

    public function forStop(TourStop $stop): static
    {
        return $this->state(fn (): array => ['tour_stop_id' => $stop->id]);
    }

    /**
     * Service de commande d'une organisation donnée : l'API refuse de planifier
     * le service d'un autre transporteur.
     */
    public function forOrganization(string $organizationId): static
    {
        return $this->state(fn (): array => [
            'order_service_id' => OrderService::factory()->state([
                'order_id' => Order::factory()->state(['organization_id' => $organizationId]),
            ]),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active_assignment' => false]);
    }
}
