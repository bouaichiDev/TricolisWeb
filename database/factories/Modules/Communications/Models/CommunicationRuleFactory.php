<?php

namespace Database\Factories\Modules\Communications\Models;

use App\Modules\Communications\Enums\CommunicationEventType;
use App\Modules\Communications\Enums\RecipientRole;
use App\Modules\Communications\Models\CommunicationRule;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Templates\Models\Template;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunicationRule>
 */
class CommunicationRuleFactory extends Factory
{
    public function modelName(): string
    {
        return CommunicationRule::class;
    }

    public function definition(): array
    {
        $template = Template::factory();

        return [
            'template_id' => $template,
            // L'organisation est celle du modele : l'API force cette coherence,
            // un jeu qui la romprait serait invalide.
            'organization_id' => fn (array $attributes): string => Template::whereKey($attributes['template_id'])->value('organization_id'),
            'service_id' => null,
            'event_type' => CommunicationEventType::SERVICE_COMPLETED,
            'recipient_role' => RecipientRole::CUSTOMER,
            'delay_value' => 0,
            'delay_unit' => 'minutes',
            'conditions' => null,
            'is_automatic' => true,
            'is_active' => true,
        ];
    }

    public function forTemplate(Template $template): static
    {
        return $this->state(fn (): array => [
            'template_id' => $template->id,
            'organization_id' => $template->organization_id,
        ]);
    }

    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn (): array => [
            'organization_id' => $organization->id,
            'template_id' => Template::factory()->forOrganization($organization),
        ]);
    }
}
