<?php

use App\Modules\Communications\Models\CommunicationRule;
use App\Modules\Communications\Models\OrderCommunication;
use App\Modules\Orders\Models\Service;
use App\Modules\Templates\Models\Template;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->template = Template::factory()->forOrganization($this->organization)->create();

    $this->payload = fn (array $o = []): array => array_merge([
        'templateId' => $this->template->id,
        'eventType' => 'service_completed',
        'recipientRole' => 'customer',
        'delayValue' => 0,
        'delayUnit' => 'minutes',
    ], $o);
});

describe('rule creation', function (): void {
    it('creates a rule bound to a template', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/communication-rules', ($this->payload)())
            ->assertCreated()
            ->assertJsonPath('data.templateId', $this->template->id)
            ->assertJsonPath('data.eventType', 'service_completed')
            ->assertJsonPath('data.template.code', $this->template->code);
    });

    it('requires a template', function (): void {
        $payload = ($this->payload)();
        unset($payload['templateId']);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/communication-rules', $payload)
            ->assertStatus(422)->assertJsonValidationErrors('templateId');
    });

    it('refuses a template from another organization', function (): void {
        $foreign = Template::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/communication-rules', ($this->payload)(['templateId' => $foreign->id]))
            ->assertStatus(422)->assertJsonValidationErrors('templateId');
    });

    it('refuses a service inconsistent with the template service', function (): void {
        $serviceA = Service::factory()->create(['organization_id' => $this->organization->id]);
        $serviceB = Service::factory()->create(['organization_id' => $this->organization->id]);
        $scoped = Template::factory()->forService($serviceA)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/communication-rules', ($this->payload)([
                'templateId' => $scoped->id, 'serviceId' => $serviceB->id,
            ]))
            ->assertStatus(422)->assertJsonValidationErrors('serviceId');

        // Le meme service que le modele passe.
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/communication-rules', ($this->payload)([
                'templateId' => $scoped->id, 'serviceId' => $serviceA->id,
            ]))
            ->assertCreated();
    });

    it('refuses an event type or recipient role outside the enums', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/communication-rules', ($this->payload)(['eventType' => 'invoice_paid']))
            ->assertStatus(422)->assertJsonValidationErrors('eventType');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/communication-rules', ($this->payload)(['recipientRole' => 'driver']))
            ->assertStatus(422)->assertJsonValidationErrors('recipientRole');
    });

    it('refuses an unsupported delay unit and a negative delay', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/communication-rules', ($this->payload)(['delayUnit' => 'fortnights']))
            ->assertStatus(422)->assertJsonValidationErrors('delayUnit');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/communication-rules', ($this->payload)(['delayValue' => -5]))
            ->assertStatus(422)->assertJsonValidationErrors('delayValue');
    });
});

describe('rule conditions', function (): void {
    it('accepts a flat conjunction', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/communication-rules', ($this->payload)([
                'conditions' => ['all' => [['field' => 'order_status', 'operator' => 'eq', 'value' => 'confirmed']]],
            ]))
            ->assertCreated()
            ->assertJsonPath('data.conditions.all.0.field', 'order_status');
    });

    it('refuses a dangerous or unsupported condition', function (): void {
        $dangerous = [
            ['all' => [['field' => 'x', 'operator' => 'eval', 'value' => 'phpinfo()']]],
            ['all' => [['field' => 'order.customer.email', 'operator' => 'eq', 'value' => 'a@b.test']]],
            ['any' => [['field' => 'x', 'operator' => 'eq', 'value' => 1]]],
            ['all' => [['field' => 'x', 'operator' => 'eq', 'value' => ['nested' => true]]]],
        ];

        foreach ($dangerous as $conditions) {
            $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
                ->postJson('/api/v1/communication-rules', ($this->payload)(['conditions' => $conditions]))
                ->assertStatus(422);
        }
    });

    it('accepts null conditions for an unconditional rule', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/communication-rules', ($this->payload)(['conditions' => null]))
            ->assertCreated()->assertJsonPath('data.conditions', null);
    });
});

describe('rule crud, scope and deletion', function (): void {
    it('updates a rule', function (): void {
        $rule = CommunicationRule::factory()->forTemplate($this->template)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/communication-rules/{$rule->id}", [
                'delayValue' => 30, 'delayUnit' => 'hours', 'isAutomatic' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.delayValue', 30)
            ->assertJsonPath('data.delayUnit', 'hours')
            ->assertJsonPath('data.isAutomatic', false);
    });

    it('refuses to delete a rule that produced communications', function (): void {
        $rule = CommunicationRule::factory()->forTemplate($this->template)->create();
        OrderCommunication::factory()->create([
            'organization_id' => $this->organization->id,
            'communication_rule_id' => $rule->id,
        ]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/communication-rules/{$rule->id}")->assertStatus(409);
    });

    it('deletes an unused rule and journals it', function (): void {
        $rule = CommunicationRule::factory()->forTemplate($this->template)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/communication-rules/{$rule->id}")->assertNoContent();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'communication_rule.deleted', 'entity_type' => 'communication_rule', 'entity_id' => $rule->id,
        ]);
    });

    it('hides a rule from another organization', function (): void {
        $foreign = CommunicationRule::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/communication-rules/{$foreign->id}")->assertNotFound();
    });

    it('lists and filters', function (): void {
        CommunicationRule::factory()->forTemplate($this->template)->create([
            'event_type' => 'order_created', 'delay_unit' => 'days', 'is_active' => false,
        ]);
        CommunicationRule::factory(2)->forTemplate($this->template)->create();
        CommunicationRule::factory(3)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/communication-rules')->assertOk()->assertJsonCount(3, 'data');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/communication-rules?eventType=order_created')->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/communication-rules?isActive=0')->assertOk()->assertJsonCount(1, 'data');
    });

    it('has no invented columns', function (): void {
        $columns = Schema::getColumnListing('communication_rules');

        expect($columns)->not->toContain('cron_expression')
            ->and($columns)->not->toContain('scheduled_expression')
            ->and($columns)->not->toContain('priority')
            ->and($columns)->not->toContain('deleted_at');
    });
});
