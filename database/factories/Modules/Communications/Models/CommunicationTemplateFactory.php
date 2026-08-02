<?php

namespace Database\Factories\Modules\Communications\Models;

use App\Modules\Communications\Enums\CommunicationChannel;
use App\Modules\Communications\Enums\CommunicationTemplateType;
use App\Modules\Communications\Models\CommunicationTemplate;
use App\Modules\Orders\Models\Service;
use App\Modules\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CommunicationTemplate>
 */
class CommunicationTemplateFactory extends Factory
{
    public function modelName(): string
    {
        return CommunicationTemplate::class;
    }

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'service_id' => null,
            'code' => 'tpl-'.Str::lower(Str::random(8)),
            'name' => 'Confirmation de livraison',
            'channel' => CommunicationChannel::EMAIL,
            'template_type' => CommunicationTemplateType::DELIVERY_CONFIRMATION,
            'subject_template' => 'Votre livraison {{ order_number }}',
            'body_template' => 'Bonjour {{ customer_name }}, votre commande {{ order_number }} est livrée.',
            'available_variables' => ['order_number', 'customer_name'],
            'language' => 'fr',
            'is_default' => false,
            'is_active' => true,
        ];
    }

    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn (): array => ['organization_id' => $organization->id]);
    }

    public function forService(Service $service): static
    {
        return $this->state(fn (): array => [
            'organization_id' => $service->organization_id,
            'service_id' => $service->id,
        ]);
    }

    /**
     * Modele SMS : pas d'objet, le canal n'en transporte pas.
     */
    public function sms(): static
    {
        return $this->state(fn (): array => [
            'channel' => CommunicationChannel::SMS,
            'subject_template' => null,
            'body_template' => 'Commande {{ order_number }} livree.',
            'available_variables' => ['order_number'],
        ]);
    }

    public function internal(): static
    {
        return $this->state(fn (): array => [
            'channel' => CommunicationChannel::INTERNAL_NOTIFICATION,
            'subject_template' => null,
        ]);
    }
}
