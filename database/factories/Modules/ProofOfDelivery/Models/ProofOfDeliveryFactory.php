<?php

namespace Database\Factories\Modules\ProofOfDelivery\Models;

use App\Modules\Documents\Models\Document;
use App\Modules\Orders\Models\Order;
use App\Modules\ProofOfDelivery\Models\ProofOfDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProofOfDelivery>
 */
class ProofOfDeliveryFactory extends Factory
{
    public function modelName(): string
    {
        return ProofOfDelivery::class;
    }

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'order_service_id' => null,
            'tour_stop_id' => null,
            'recipient_name' => fake()->name(),
            'signature_document_id' => null,
            'photo_document_id' => null,
            'remark' => null,
            'delivered_at' => now(),
            'created_by' => null,
        ];
    }

    public function forOrder(Order $order): static
    {
        return $this->state(fn (): array => ['order_id' => $order->id]);
    }

    /**
     * Documents crees dans l'organisation de la commande : l'API refuse ceux
     * d'une autre organisation.
     */
    public function withDocuments(?Document $signature = null, ?Document $photo = null): static
    {
        return $this->state(function (array $attributes) use ($signature, $photo): array {
            $organizationId = Order::whereKey($attributes['order_id'])->value('organization_id');

            return [
                'signature_document_id' => $signature?->id
                    ?? Document::factory()->create(['organization_id' => $organizationId])->id,
                'photo_document_id' => $photo?->id
                    ?? Document::factory()->create(['organization_id' => $organizationId])->id,
            ];
        });
    }
}
