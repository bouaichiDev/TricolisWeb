<?php

namespace Database\Factories\Modules\Communications\Models;

use App\Modules\Communications\Enums\CommunicationChannel;
use App\Modules\Communications\Enums\CommunicationStatus;
use App\Modules\Communications\Enums\RecipientRole;
use App\Modules\Communications\Models\OrderCommunication;
use App\Modules\Orders\Models\Order;
use App\Modules\Templates\Enums\TemplateType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderCommunication>
 */
class OrderCommunicationFactory extends Factory
{
    public function modelName(): string
    {
        return OrderCommunication::class;
    }

    public function definition(): array
    {
        $order = Order::factory();

        return [
            'order_id' => $order,
            // L'organisation est celle de la commande : l'API force cette
            // coherence.
            'organization_id' => fn (array $attributes): string => Order::whereKey($attributes['order_id'])->value('organization_id'),
            'template_id' => null,
            'communication_rule_id' => null,
            'channel' => CommunicationChannel::EMAIL,
            'communication_type' => TemplateType::DELIVERY_CONFIRMATION,
            'recipient_role' => RecipientRole::CUSTOM,
            'recipient_name' => 'Marie Dupont',
            'recipient_email' => 'marie.dupont@example.test',
            'recipient_phone' => null,
            'subject' => 'Votre livraison',
            'body' => 'Bonjour, votre commande est livree.',
            'template_variables' => null,
            'status' => CommunicationStatus::DRAFT,
            'scheduled_at' => null,
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

    public function withStatus(CommunicationStatus $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }

    public function scheduled(?string $at = null): static
    {
        return $this->state(fn (): array => [
            'status' => CommunicationStatus::SCHEDULED,
            'scheduled_at' => $at ?? now()->subMinute(),
        ]);
    }

    public function queued(): static
    {
        return $this->state(fn (): array => [
            'status' => CommunicationStatus::QUEUED,
            'queued_at' => now(),
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn (): array => [
            'status' => CommunicationStatus::SENT,
            'queued_at' => now()->subMinute(),
            'sent_at' => now(),
            'provider_message_id' => 'msg-'.fake()->uuid(),
        ]);
    }

    public function failed(string $message = 'Canal indisponible'): static
    {
        return $this->state(fn (): array => [
            'status' => CommunicationStatus::FAILED,
            'queued_at' => now()->subMinute(),
            'failed_at' => now(),
            'error_message' => $message,
        ]);
    }

    /**
     * Communication SMS : telephone renseigne, pas d'objet.
     */
    public function sms(): static
    {
        return $this->state(fn (): array => [
            'channel' => CommunicationChannel::SMS,
            'subject' => null,
            'recipient_email' => null,
            'recipient_phone' => '+33600000000',
        ]);
    }
}
