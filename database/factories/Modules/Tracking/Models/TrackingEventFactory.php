<?php

namespace Database\Factories\Modules\Tracking\Models;

use App\Modules\Orders\Models\Order;
use App\Modules\Tracking\Models\TrackingEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrackingEvent>
 */
class TrackingEventFactory extends Factory
{
    public function modelName(): string
    {
        return TrackingEvent::class;
    }

    public function definition(): array
    {
        $order = Order::factory();

        return [
            'order_id' => $order,
            // L'organisation est celle de la commande : l'API refuse toute
            // autre valeur, un jeu incoherent serait inutilisable.
            'organization_id' => fn (array $attributes): string => Order::whereKey($attributes['order_id'])->value('organization_id'),
            'order_service_id' => null,
            'tour_id' => null,
            'tour_stop_id' => null,
            'event_type' => 'pickup',
            'status' => 'done',
            'description' => null,
            'latitude' => null,
            'longitude' => null,
            'occurred_at' => now(),
            'created_by' => null,
        ];
    }

    public function forOrder(Order $order): static
    {
        return $this->state(fn (): array => [
            'order_id' => $order->id,
            'organization_id' => $order->organization_id,
        ]);
    }

    /**
     * Coordonnees valides : latitude dans [-90, 90], longitude dans [-180, 180].
     */
    public function located(float $latitude = 33.5731, float $longitude = -7.5898): static
    {
        return $this->state(fn (): array => [
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);
    }
}
