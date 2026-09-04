<?php

use App\Modules\Audit\Models\AuditLog;
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
            ->postJson('/api/v1/templates', ($this->payload)())
            ->assertCreated()
            ->assertJsonPath('data.serviceId', null)
            ->assertJsonPath('data.channel', 'email')
            ->assertJsonPath('data.availableVariables', ['order_number', 'customer_name']);
    });

    it('accepts a service of the same organization', function (): void {
        $service = Service::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/templates', ($this->payload)(['serviceId' => $service->id]))
            ->assertCreated()->assertJsonPath('data.serviceId', $service->id);
    });

    it('refuses a service from another organization', function (): void {
        $foreign = Service::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/templates', ($this->payload)(['serviceId' => $foreign->id]))
            ->assertStatus(422)->assertJsonValidationErrors('serviceId');
    });

    it('refuses a duplicated code inside the organization but allows it elsewhere', function (): void {
        Template::factory()->forOrganization($this->organization)->create(['code' => 'delivery-confirmation']);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/templates', ($this->payload)())
            ->assertStatus(422)->assertJsonValidationErrors('code');

        // Le meme code chez un autre transporteur reste libre.
        expect(Template::factory()->create(['code' => 'delivery-confirmation'])->exists)->toBeTrue();
    });

    it('requires a subject for email but not for sms', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/templates', ($this->payload)(['subjectTemplate' => null]))
            ->assertStatus(422)->assertJsonValidationErrors('subjectTemplate');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/templates', ($this->payload)([
                'code' => 'sms-arrival', 'channel' => 'sms', 'subjectTemplate' => null,
            ]))
            ->assertCreated()->assertJsonPath('data.subjectTemplate', null);
    });

    it('refuses a channel or type outside the enums', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/templates', ($this->payload)(['channel' => 'telegram']))
            ->assertStatus(422)->assertJsonValidationErrors('channel');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/templates', ($this->payload)(['templateType' => 'invoice_sent']))
            ->assertStatus(422)->assertJsonValidationErrors('templateType');
    });
});

describe('available variables', function (): void {
    it('accepts a dotted path, which a facture needs to name its data', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/templates', ($this->payload)([
                'code' => 'DOTTED',
                'availableVariables' => ['invoice.invoiceNumber', 'invoice.lines', 'customer.name'],
            ]))
            ->assertStatus(201)
            ->assertJsonPath('data.availableVariables.0', 'invoice.invoiceNumber');
    });

    it('refuses a variable name carrying an expression or a separator', function (): void {
        foreach ([['$secret'], ['nom variable'], ['file()'], ['a.b.c.d.e'], ['../etc/passwd'], ['Model::find']] as $variables) {
            $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
                ->postJson('/api/v1/templates', ($this->payload)([
                    'code' => 'x-'.md5(json_encode($variables)),
                    'availableVariables' => $variables,
                ]))
                ->assertStatus(422)->assertJsonValidationErrors('availableVariables.0');
        }
    });

    it('refuses an object instead of a list', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/templates', ($this->payload)([
                'availableVariables' => ['order_number' => 'Numéro'],
            ]))
            ->assertStatus(422)->assertJsonValidationErrors('availableVariables');
    });

    it('refuses a duplicated variable', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/templates', ($this->payload)([
                'availableVariables' => ['order_number', 'order_number'],
            ]))
            ->assertStatus(422)->assertJsonValidationErrors('availableVariables.1');
    });
});

describe('template crud, scope and deletion', function (): void {
    it('updates a template but never its code', function (): void {
        $template = Template::factory()->forOrganization($this->organization)->create(['code' => 'origine']);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/templates/{$template->id}", [
                'name' => 'Nouveau nom', 'code' => 'renomme', 'isActive' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Nouveau nom')
            ->assertJsonPath('data.isActive', false)
            ->assertJsonPath('data.code', 'origine');
    });

    it('refuses to delete a template used by a rule', function (): void {
        $template = Template::factory()->forOrganization($this->organization)->create();
        CommunicationRule::factory()->forTemplate($template)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/templates/{$template->id}")
            ->assertStatus(409);

        $this->assertDatabaseHas('templates', ['id' => $template->id]);
    });

    it('refuses to delete a template that produced communications', function (): void {
        $template = Template::factory()->forOrganization($this->organization)->create();
        OrderCommunication::factory()->create([
            'organization_id' => $this->organization->id,
            'template_id' => $template->id,
        ]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/templates/{$template->id}")
            ->assertStatus(409);
    });

    it('deletes an unused template and journals it', function (): void {
        $template = Template::factory()->forOrganization($this->organization)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/templates/{$template->id}")->assertNoContent();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'template.deleted', 'entity_id' => $template->id,
        ]);
    });

    it('hides a template from another organization', function (): void {
        $foreign = Template::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/templates/{$foreign->id}")->assertNotFound();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/templates/{$foreign->id}", ['name' => 'X'])->assertNotFound();
    });

    it('lists, searches, filters and refuses an unlisted sort', function (): void {
        Template::factory()->forOrganization($this->organization)->create([
            'code' => 'zzz-rappel', 'channel' => 'sms', 'subject_template' => null, 'is_active' => false,
        ]);
        Template::factory(2)->forOrganization($this->organization)->create();
        Template::factory(3)->create();

        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/templates')->assertOk();

        // Ce qui compte est la portee, pas le nombre : les modeles d'un autre
        // transporteur ne doivent pas y figurer. Figer un total cassait des que
        // le semis en posait un de plus — celui de reinitialisation, par
        // exemple — sans que rien ne soit reellement casse.
        expect($response->json('data.*.id'))->toEqualCanonicalizing(
            Template::where('organization_id', $this->organization->id)->pluck('id')->all(),
        );

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/templates?search=zzz')->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/templates?channel=sms')->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/templates?sort=body_template')->assertStatus(422);
    });

    it('journals the creation', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/templates', ($this->payload)())->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'template.created',
            'entity_type' => 'template',
            'entity_id' => $response->json('data.id'),
        ]);

        expect(AuditLog::where('entity_type', 'App\Modules\Templates\Models\Template')->count())->toBe(0);
    });

    it('has no invented columns or tables', function (): void {
        $columns = Schema::getColumnListing('templates');

        // `customer_id` est attendu depuis la Phase 9 : c'est lui qui porte le
        // modele propre a un client. Les trois autres restent absents.
        expect($columns)->toContain('customer_id')
            ->and($columns)->not->toContain('deleted_at')
            ->and($columns)->not->toContain('priority')
            ->and($columns)->not->toContain('metadata');

        // Les sept premieres sont celles que le §0.1 interdit nommement : une
        // seule table de modeles gouverne toute la plateforme, et en ouvrir une
        // par usage ferait diverger les deux au premier correctif.
        $forbidden = [
            'invoice_templates', 'customer_invoice_templates', 'invoice_template_lines',
            'email_templates', 'sms_templates', 'whatsapp_templates', 'document_templates',
            'communication_recipients', 'notifications', 'email_logs', 'sms_logs',
            'whatsapp_logs', 'webhooks', 'message_threads', 'communication_status_histories',
        ];

        foreach ($forbidden as $table) {
            expect(Schema::hasTable($table))->toBeFalse();
        }
    });
});

/**
 * Un e-mail se redige souvent en HTML, un SMS jamais.
 *
 * Sans `bodyFormat`, le serveur ne savait pas s'il devait echapper le corps :
 * le destinataire recevait des balises en clair ou un texte sans mise en forme,
 * selon le hasard de l'implementation d'envoi.
 */
it('stores and returns the body format of a template', function (): void {
    $created = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->postJson('/api/v1/templates', [
            'code' => 'HTML_TEMPLATE', 'name' => 'Modèle riche',
            'channel' => 'email', 'templateType' => 'custom', 'language' => 'fr',
            'subjectTemplate' => 'Sujet', 'bodyTemplate' => '<p>Bonjour</p>',
            'bodyFormat' => 'html',
        ]);

    $created->assertCreated()->assertJsonPath('data.bodyFormat', 'html');
    $this->assertDatabaseHas('templates', [
        'id' => $created->json('data.id'), 'body_format' => 'html',
    ]);
});

/** Sans precision, le corps reste du texte : une migration ne change rien. */
it('defaults the body format to text', function (): void {
    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->postJson('/api/v1/templates', [
            'code' => 'TEXT_TEMPLATE', 'name' => 'Modèle simple',
            'channel' => 'sms', 'templateType' => 'custom', 'language' => 'fr',
            'bodyTemplate' => 'Passage demain.',
        ])
        ->assertCreated()
        ->assertJsonPath('data.bodyFormat', 'text');
});

it('refuses an unknown body format', function (): void {
    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->postJson('/api/v1/templates', [
            'code' => 'BAD_FORMAT', 'name' => 'Format inconnu',
            'channel' => 'email', 'templateType' => 'custom', 'language' => 'fr',
            'subjectTemplate' => 'Sujet', 'bodyTemplate' => 'Corps',
            'bodyFormat' => 'markdown',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('bodyFormat');
});
