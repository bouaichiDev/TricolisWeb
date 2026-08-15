<?php

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Communications\Models\CommunicationRule;
use App\Modules\Communications\Models\CommunicationTemplate;
use App\Modules\Communications\Models\OrderCommunication;
use App\Modules\Orders\Models\Service;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];

    $this->payload = fn (array $o = []): array => array_merge([
        'code' => 'delivery-confirmation',
        'name' => 'Confirmation de livraison',
        'channel' => 'email',
        'templateType' => 'delivery_confirmation',
        'subjectTemplate' => 'Commande {{ order_number }}',
        'bodyTemplate' => 'Bonjour {{ customer_name }}.',
        'language' => 'fr',
        'availableVariables' => ['order_number', 'customer_name'],
    ], $o);
});

describe('template creation', function (): void {
    it('creates a template without a service', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/communication-templates', ($this->payload)())
            ->assertCreated()
            ->assertJsonPath('data.serviceId', null)
            ->assertJsonPath('data.channel', 'email')
            ->assertJsonPath('data.availableVariables', ['order_number', 'customer_name']);
    });

    it('accepts a service of the same organization', function (): void {
        $service = Service::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/communication-templates', ($this->payload)(['serviceId' => $service->id]))
            ->assertCreated()->assertJsonPath('data.serviceId', $service->id);
    });

    it('refuses a service from another organization', function (): void {
        $foreign = Service::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/communication-templates', ($this->payload)(['serviceId' => $foreign->id]))
            ->assertStatus(422)->assertJsonValidationErrors('serviceId');
    });

    it('refuses a duplicated code inside the organization but allows it elsewhere', function (): void {
        CommunicationTemplate::factory()->forOrganization($this->organization)->create(['code' => 'delivery-confirmation']);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/communication-templates', ($this->payload)())
            ->assertStatus(422)->assertJsonValidationErrors('code');

        // Le meme code chez un autre transporteur reste libre.
        expect(CommunicationTemplate::factory()->create(['code' => 'delivery-confirmation'])->exists)->toBeTrue();
    });

    it('requires a subject for email but not for sms', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/communication-templates', ($this->payload)(['subjectTemplate' => null]))
            ->assertStatus(422)->assertJsonValidationErrors('subjectTemplate');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/communication-templates', ($this->payload)([
                'code' => 'sms-arrival', 'channel' => 'sms', 'subjectTemplate' => null,
            ]))
            ->assertCreated()->assertJsonPath('data.subjectTemplate', null);
    });

    it('refuses a channel or type outside the enums', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/communication-templates', ($this->payload)(['channel' => 'telegram']))
            ->assertStatus(422)->assertJsonValidationErrors('channel');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/communication-templates', ($this->payload)(['templateType' => 'invoice_sent']))
            ->assertStatus(422)->assertJsonValidationErrors('templateType');
    });
});

describe('available variables', function (): void {
    it('refuses a variable name carrying a path or an expression', function (): void {
        foreach ([['order.customer.name'], ['$secret'], ['nom variable'], ['file()']] as $variables) {
            $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
                ->postJson('/api/v1/communication-templates', ($this->payload)([
                    'code' => 'x-'.md5(json_encode($variables)),
                    'availableVariables' => $variables,
                ]))
                ->assertStatus(422)->assertJsonValidationErrors('availableVariables.0');
        }
    });

    it('refuses an object instead of a list', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/communication-templates', ($this->payload)([
                'availableVariables' => ['order_number' => 'Numéro'],
            ]))
            ->assertStatus(422)->assertJsonValidationErrors('availableVariables');
    });

    it('refuses a duplicated variable', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/communication-templates', ($this->payload)([
                'availableVariables' => ['order_number', 'order_number'],
            ]))
            ->assertStatus(422)->assertJsonValidationErrors('availableVariables.1');
    });
});

describe('template crud, scope and deletion', function (): void {
    it('updates a template but never its code', function (): void {
        $template = CommunicationTemplate::factory()->forOrganization($this->organization)->create(['code' => 'origine']);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/communication-templates/{$template->id}", [
                'name' => 'Nouveau nom', 'code' => 'renomme', 'isActive' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Nouveau nom')
            ->assertJsonPath('data.isActive', false)
            ->assertJsonPath('data.code', 'origine');
    });

    it('refuses to delete a template used by a rule', function (): void {
        $template = CommunicationTemplate::factory()->forOrganization($this->organization)->create();
        CommunicationRule::factory()->forTemplate($template)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/communication-templates/{$template->id}")
            ->assertStatus(409);

        $this->assertDatabaseHas('communication_templates', ['id' => $template->id]);
    });

    it('refuses to delete a template that produced communications', function (): void {
        $template = CommunicationTemplate::factory()->forOrganization($this->organization)->create();
        OrderCommunication::factory()->create([
            'organization_id' => $this->organization->id,
            'template_id' => $template->id,
        ]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/communication-templates/{$template->id}")
            ->assertStatus(409);
    });

    it('deletes an unused template and journals it', function (): void {
        $template = CommunicationTemplate::factory()->forOrganization($this->organization)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/communication-templates/{$template->id}")->assertNoContent();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'communication_template.deleted', 'entity_id' => $template->id,
        ]);
    });

    it('hides a template from another organization', function (): void {
        $foreign = CommunicationTemplate::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/communication-templates/{$foreign->id}")->assertNotFound();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/communication-templates/{$foreign->id}", ['name' => 'X'])->assertNotFound();
    });

    it('lists, searches, filters and refuses an unlisted sort', function (): void {
        CommunicationTemplate::factory()->forOrganization($this->organization)->create([
            'code' => 'zzz-rappel', 'channel' => 'sms', 'subject_template' => null, 'is_active' => false,
        ]);
        CommunicationTemplate::factory(2)->forOrganization($this->organization)->create();
        CommunicationTemplate::factory(3)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/communication-templates')->assertOk()->assertJsonCount(3, 'data');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/communication-templates?search=zzz')->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/communication-templates?channel=sms')->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/communication-templates?sort=body_template')->assertStatus(422);
    });

    it('journals the creation', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/communication-templates', ($this->payload)())->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'communication_template.created',
            'entity_type' => 'communication_template',
            'entity_id' => $response->json('data.id'),
        ]);

        expect(AuditLog::where('entity_type', 'App\Modules\Communications\Models\CommunicationTemplate')->count())->toBe(0);
    });

    it('has no invented columns or tables', function (): void {
        $columns = Schema::getColumnListing('communication_templates');

        expect($columns)->not->toContain('deleted_at')
            ->and($columns)->not->toContain('customer_id')
            ->and($columns)->not->toContain('priority')
            ->and($columns)->not->toContain('metadata');

        foreach (['communication_recipients', 'notifications', 'email_logs', 'sms_logs', 'whatsapp_logs', 'webhooks', 'message_threads', 'communication_status_histories'] as $table) {
            expect(Schema::hasTable($table))->toBeFalse();
        }
    });
});
