<?php

use App\Modules\Communications\Models\CommunicationRule;
use App\Modules\Communications\Models\CommunicationTemplate;
use App\Modules\Communications\Models\OrderCommunication;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;
use App\Modules\Orders\Models\OrderServiceContact;
use App\Shared\Enums\ContactRole;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];

    $this->order = Order::factory()->forOrganization($this->organization)->create();
    $this->order->customer->update(['email' => 'client@example.test', 'phone' => '+212600000000']);
    $this->orderService = OrderService::factory()->forOrder($this->order)->create();

    $this->contact = function (ContactRole $role, array $overrides = []): OrderServiceContact {
        return OrderServiceContact::create(array_merge([
            'order_service_id' => $this->orderService->id,
            'contact_id' => null,
            'contact_role' => $role,
            'first_name_snapshot' => 'Marie',
            'last_name_snapshot' => ucfirst($role->value),
            'phone_snapshot' => '+212611111111',
            'mobile_snapshot' => '+212622222222',
            'email_snapshot' => $role->value.'@example.test',
            'is_primary' => true,
        ], $overrides));
    };

    $this->payload = fn (array $o = []): array => array_merge([
        'orderId' => $this->order->id,
        'channel' => 'email',
        'communicationType' => 'delivery_confirmation',
        'recipientRole' => 'custom',
        'recipientName' => 'Destinataire libre',
        'recipientEmail' => 'libre@example.test',
        'body' => 'Bonjour, votre commande est livrée.',
        'subject' => 'Livraison',
    ], $o);

    $this->post = fn (array $o = []) => $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->postJson('/api/v1/order-communications', ($this->payload)($o));
});

describe('communication creation', function (): void {
    it('creates a manual communication without a template', function (): void {
        ($this->post)()
            ->assertCreated()
            ->assertJsonPath('data.templateId', null)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.body', 'Bonjour, votre commande est livrée.');
    });

    it('refuses a manual communication without a body', function (): void {
        $payload = ($this->payload)();
        unset($payload['body']);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/order-communications', $payload)
            ->assertStatus(422)->assertJsonValidationErrors('body');
    });

    it('renders the template and freezes the result', function (): void {
        $template = CommunicationTemplate::factory()->forOrganization($this->organization)->create();

        $response = ($this->post)([
            'templateId' => $template->id,
            'templateVariables' => ['order_number' => 'CMD-42', 'customer_name' => 'Marie'],
        ])->assertCreated();

        expect($response->json('data.subject'))->toBe('Votre livraison CMD-42')
            ->and($response->json('data.body'))->toBe('Bonjour Marie, votre commande CMD-42 est livrée.')
            ->and($response->json('data.templateVariables'))->toBe(['order_number' => 'CMD-42', 'customer_name' => 'Marie']);
    });

    it('keeps the snapshot when the template changes afterwards', function (): void {
        $template = CommunicationTemplate::factory()->forOrganization($this->organization)->create();

        $id = ($this->post)([
            'templateId' => $template->id,
            'templateVariables' => ['order_number' => 'CMD-42', 'customer_name' => 'Marie'],
        ])->assertCreated()->json('data.id');

        $template->update(['body_template' => 'Texte entièrement réécrit.', 'available_variables' => []]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/order-communications/{$id}")
            ->assertOk()
            ->assertJsonPath('data.body', 'Bonjour Marie, votre commande CMD-42 est livrée.');
    });

    it('derives the template from the rule when only the rule is given', function (): void {
        $template = CommunicationTemplate::factory()->forOrganization($this->organization)->create();
        $rule = CommunicationRule::factory()->forTemplate($template)->create();

        ($this->post)([
            'communicationRuleId' => $rule->id,
            'templateVariables' => ['order_number' => 'CMD-7', 'customer_name' => 'Ali'],
        ])
            ->assertCreated()
            ->assertJsonPath('data.templateId', $template->id)
            ->assertJsonPath('data.communicationRuleId', $rule->id);
    });

    it('refuses an unknown variable in the template', function (): void {
        $template = CommunicationTemplate::factory()->forOrganization($this->organization)
            ->create(['body_template' => 'Bonjour {{ secret_field }}.']);

        ($this->post)([
            'templateId' => $template->id,
            'templateVariables' => ['order_number' => 'CMD-1', 'customer_name' => 'Marie'],
        ])->assertStatus(422);
    });

    it('refuses a template or an order from another organization', function (): void {
        $foreignTemplate = CommunicationTemplate::factory()->create();
        $foreignOrder = Order::factory()->create();

        ($this->post)(['templateId' => $foreignTemplate->id])
            ->assertStatus(422)->assertJsonValidationErrors('templateId');

        ($this->post)(['orderId' => $foreignOrder->id])
            ->assertStatus(422)->assertJsonValidationErrors('orderId');
    });

    it('schedules a communication when a date is given', function (): void {
        ($this->post)(['scheduledAt' => now()->addHour()->toIso8601String()])
            ->assertCreated()->assertJsonPath('data.status', 'scheduled');
    });

    it('ignores execution fields supplied by the caller', function (): void {
        $response = ($this->post)([
            'status' => 'sent',
            'sentAt' => now()->toIso8601String(),
            'providerMessageId' => 'forge',
        ])->assertCreated();

        expect($response->json('data.status'))->toBe('draft')
            ->and($response->json('data.sentAt'))->toBeNull()
            ->and($response->json('data.providerMessageId'))->toBeNull();
    });

    it('creates through the nested order route', function (): void {
        $payload = ($this->payload)();
        unset($payload['orderId']);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/orders/{$this->order->id}/communications", $payload)
            ->assertCreated()->assertJsonPath('data.orderId', $this->order->id);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/orders/{$this->order->id}/communications")
            ->assertOk()->assertJsonCount(1, 'data');
    });
});

describe('recipient resolution', function (): void {
    it('resolves the customer', function (): void {
        ($this->post)(['recipientRole' => 'customer'])
            ->assertCreated()
            ->assertJsonPath('data.recipientName', $this->order->customer->name)
            ->assertJsonPath('data.recipientEmail', 'client@example.test')
            ->assertJsonPath('data.recipientPhone', '+212600000000');
    });

    it('resolves each order contact role', function (): void {
        $roles = [
            'load_contact' => ContactRole::LOAD,
            'delivery_contact' => ContactRole::DELIVERY,
            'billing_contact' => ContactRole::BILLING,
        ];

        foreach ($roles as $recipientRole => $contactRole) {
            ($this->contact)($contactRole);

            ($this->post)(['recipientRole' => $recipientRole])
                ->assertCreated()
                ->assertJsonPath('data.recipientName', 'Marie '.ucfirst($contactRole->value))
                ->assertJsonPath('data.recipientEmail', $contactRole->value.'@example.test')
                ->assertJsonPath('data.recipientPhone', '+212611111111');
        }
    });

    it('resolves the authenticated internal user', function (): void {
        ($this->post)(['recipientRole' => 'internal_user'])
            ->assertCreated()
            ->assertJsonPath('data.recipientName', trim($this->user->first_name.' '.$this->user->last_name))
            ->assertJsonPath('data.recipientEmail', $this->user->email)
            ->assertJsonPath('data.recipientPhone', null);
    });

    it('refuses when no contact carries the requested role', function (): void {
        ($this->post)(['recipientRole' => 'delivery_contact'])
            ->assertStatus(422)->assertJsonValidationErrors('recipientRole');
    });

    it('ignores caller supplied contact details for a non custom role', function (): void {
        ($this->post)([
            'recipientRole' => 'customer',
            'recipientName' => 'Usurpateur',
            'recipientEmail' => 'usurpateur@example.test',
        ])
            ->assertCreated()
            ->assertJsonPath('data.recipientName', $this->order->customer->name)
            ->assertJsonPath('data.recipientEmail', 'client@example.test');
    });

    it('requires an explicit name for the custom role', function (): void {
        $payload = ($this->payload)();
        unset($payload['recipientName']);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/order-communications', $payload)
            ->assertStatus(422)->assertJsonValidationErrors('recipientName');
    });

    it('prefers the primary contact over the others', function (): void {
        ($this->contact)(ContactRole::DELIVERY, [
            'is_primary' => false, 'first_name_snapshot' => 'Secondaire', 'email_snapshot' => 'second@example.test',
        ]);
        ($this->contact)(ContactRole::DELIVERY, [
            'is_primary' => true, 'first_name_snapshot' => 'Principal', 'email_snapshot' => 'principal@example.test',
        ]);

        ($this->post)(['recipientRole' => 'delivery_contact'])
            ->assertCreated()->assertJsonPath('data.recipientEmail', 'principal@example.test');
    });

    it('falls back to the mobile when no landline is recorded', function (): void {
        ($this->contact)(ContactRole::LOAD, ['phone_snapshot' => null]);

        ($this->post)(['recipientRole' => 'load_contact'])
            ->assertCreated()->assertJsonPath('data.recipientPhone', '+212622222222');
    });
});

describe('channel and recipient consistency', function (): void {
    it('requires an email for the email channel', function (): void {
        ($this->post)(['recipientEmail' => null])
            ->assertStatus(422)->assertJsonValidationErrors('recipientEmail');
    });

    it('requires a phone for sms and whatsapp but never an email', function (): void {
        foreach (['sms', 'whatsapp'] as $channel) {
            ($this->post)(['channel' => $channel, 'recipientEmail' => null, 'recipientPhone' => null])
                ->assertStatus(422)->assertJsonValidationErrors('recipientPhone');

            ($this->post)(['channel' => $channel, 'recipientEmail' => null, 'recipientPhone' => '+212633333333'])
                ->assertCreated();
        }
    });

    it('requires neither for push and internal notifications', function (): void {
        foreach (['push_notification', 'internal_notification'] as $channel) {
            ($this->post)(['channel' => $channel, 'recipientEmail' => null, 'recipientPhone' => null])
                ->assertCreated();
        }
    });
});

describe('communication edition and deletion', function (): void {
    it('updates a draft', function (): void {
        $communication = OrderCommunication::factory()->forOrder($this->order)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/order-communications/{$communication->id}", ['subject' => 'Corrigé'])
            ->assertOk()->assertJsonPath('data.subject', 'Corrigé');
    });

    it('refuses to edit or delete a communication already sent', function (): void {
        $communication = OrderCommunication::factory()->forOrder($this->order)->sent()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/order-communications/{$communication->id}", ['subject' => 'Réécriture'])
            ->assertStatus(409);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/order-communications/{$communication->id}")->assertStatus(409);

        $this->assertDatabaseHas('order_communications', ['id' => $communication->id, 'subject' => 'Votre livraison']);
    });

    it('deletes a draft', function (): void {
        $communication = OrderCommunication::factory()->forOrder($this->order)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/order-communications/{$communication->id}")->assertNoContent();

        $this->assertDatabaseMissing('order_communications', ['id' => $communication->id]);
    });

    it('switches between draft and scheduled through the schedule date', function (): void {
        $communication = OrderCommunication::factory()->forOrder($this->order)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/order-communications/{$communication->id}", [
                'scheduledAt' => now()->addDay()->toIso8601String(),
            ])->assertOk()->assertJsonPath('data.status', 'scheduled');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/order-communications/{$communication->id}", ['scheduledAt' => null])
            ->assertOk()->assertJsonPath('data.status', 'draft');
    });

    it('hides a communication from another organization', function (): void {
        $foreign = OrderCommunication::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/order-communications/{$foreign->id}")->assertNotFound();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/order-communications/{$foreign->id}/queue")->assertNotFound();
    });

    it('lists, filters and refuses an unlisted sort', function (): void {
        OrderCommunication::factory(2)->forOrder($this->order)->create();
        OrderCommunication::factory()->forOrder($this->order)->sms()->create();
        OrderCommunication::factory(3)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/order-communications')->assertOk()->assertJsonCount(3, 'data');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/order-communications?channel=sms')->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/order-communications?sort=body')->assertStatus(422);
    });

    it('has no invented columns', function (): void {
        $columns = Schema::getColumnListing('order_communications');

        expect($columns)->not->toContain('retry_count')
            ->and($columns)->not->toContain('max_attempts')
            ->and($columns)->not->toContain('cc')
            ->and($columns)->not->toContain('bcc')
            ->and($columns)->not->toContain('reply_to')
            ->and($columns)->not->toContain('sender_email')
            ->and($columns)->not->toContain('priority')
            ->and($columns)->not->toContain('deleted_at')
            ->and($columns)->not->toContain('cancelled_at');
    });
});
