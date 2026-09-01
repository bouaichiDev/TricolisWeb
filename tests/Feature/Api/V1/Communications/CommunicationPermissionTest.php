<?php

use App\Modules\Communications\Models\CommunicationAttachment;
use App\Modules\Communications\Models\CommunicationRule;
use App\Modules\Communications\Models\OrderCommunication;
use App\Modules\Documents\Models\Document;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Models\UserRole;
use App\Modules\Orders\Models\Order;
use App\Modules\Organizations\Models\OrganizationUser;
use App\Modules\Templates\Models\Template;

beforeEach(function (): void {
    $this->seed();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];

    // Membre sans aucune permission : ni owner, ni role porteur de droits.
    $this->membership = OrganizationUser::factory()->forOrganization($this->organization)->create(['is_owner' => false]);
    $this->powerless = $this->membership->user;

    $this->order = Order::factory()->forOrganization($this->organization)->create();
    $this->template = Template::factory()->forOrganization($this->organization)->create();
    $this->rule = CommunicationRule::factory()->forTemplate($this->template)->create();
    $this->communication = OrderCommunication::factory()->forOrder($this->order)->failed()->create();
    $this->document = Document::factory()->forOrganization($this->organization)->create();
    $this->attachment = CommunicationAttachment::factory()
        ->forCommunication($this->communication)->forDocument($this->document)->create();

    $this->urls = [
        '/api/v1/templates',
        '/api/v1/communication-rules',
        '/api/v1/order-communications',
    ];

    $this->grant = function (array $codes): void {
        $role = Role::factory()->forOrganization($this->organization)->create();

        foreach (Permission::whereIn('code', $codes)->pluck('id') as $permissionId) {
            RolePermission::create(['role_id' => $role->id, 'permission_id' => $permissionId]);
        }

        UserRole::create(['organization_user_id' => $this->membership->id, 'role_id' => $role->id]);
    };
});

describe('missing permissions', function (): void {
    it('forbids reading each resource without the view permission', function (): void {
        foreach ($this->urls as $url) {
            $this->actingAs($this->powerless, 'sanctum')->withHeaders($this->headers)
                ->getJson($url)->assertForbidden();
        }

        $this->actingAs($this->powerless, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/order-communications/{$this->communication->id}/attachments")->assertForbidden();
    });

    it('forbids creating without the create permission', function (): void {
        // Les payloads sont complets : sans cela un 422 masquerait le 403 que
        // ce test cherche a prouver.
        $this->actingAs($this->powerless, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/templates', [
                'code' => 'tpl-permission', 'name' => 'Modele', 'channel' => 'sms',
                'templateType' => 'custom', 'bodyTemplate' => 'Texte.', 'language' => 'fr',
            ])->assertForbidden();

        $this->actingAs($this->powerless, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/communication-rules', [
                'templateId' => $this->template->id, 'eventType' => 'order_created',
                'recipientRole' => 'customer', 'delayUnit' => 'minutes',
            ])->assertForbidden();

        $this->actingAs($this->powerless, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/order-communications', [
                'orderId' => $this->order->id, 'channel' => 'internal_notification',
                'communicationType' => 'custom', 'recipientRole' => 'custom',
                'recipientName' => 'Libre', 'body' => 'Texte.',
            ])->assertForbidden();

        $this->actingAs($this->powerless, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/order-communications/{$this->communication->id}/attachments", [
                'documentId' => $this->document->id,
            ])->assertForbidden();
    });

    it('forbids each transition without its dedicated permission', function (): void {
        foreach (['queue', 'cancel', 'retry'] as $transition) {
            $this->actingAs($this->powerless, 'sanctum')->withHeaders($this->headers)
                ->postJson("/api/v1/order-communications/{$this->communication->id}/{$transition}")
                ->assertForbidden();
        }
    });

    it('forbids deleting an attachment without the delete permission', function (): void {
        $this->actingAs($this->powerless, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/order-communications/{$this->communication->id}/attachments/{$this->attachment->id}")
            ->assertForbidden();
    });
});

describe('granted permissions', function (): void {
    it('grants read access once the view permissions are attached', function (): void {
        ($this->grant)([
            'templates.view',
            'communication_rules.view',
            'order_communications.view',
            'communication_attachments.view',
        ]);

        foreach ($this->urls as $url) {
            $this->actingAs($this->powerless, 'sanctum')->withHeaders($this->headers)
                ->getJson($url)->assertOk();
        }

        $this->actingAs($this->powerless, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/order-communications/{$this->communication->id}/attachments")->assertOk();
    });

    it('does not let an update permission trigger a send', function (): void {
        ($this->grant)(['order_communications.view', 'order_communications.update']);

        // Modifier oui — mais expedier, annuler ou relancer, non.
        foreach (['queue', 'cancel', 'retry'] as $transition) {
            $this->actingAs($this->powerless, 'sanctum')->withHeaders($this->headers)
                ->postJson("/api/v1/order-communications/{$this->communication->id}/{$transition}")
                ->assertForbidden();
        }
    });

    it('lets retry work once its own permission is attached', function (): void {
        ($this->grant)(['order_communications.view', 'order_communications.retry']);

        $this->actingAs($this->powerless, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/order-communications/{$this->communication->id}/retry")
            ->assertOk()->assertJsonPath('data.status', 'queued');
    });
});

describe('authentication and organization context', function (): void {
    it('requires the organization header on every route', function (): void {
        $user = authUser();

        foreach ($this->urls as $url) {
            $this->actingAs($user, 'sanctum')->getJson($url)->assertForbidden();
        }
    });

    it('rejects unauthenticated access', function (): void {
        foreach ($this->urls as $url) {
            $this->getJson($url)->assertUnauthorized();
        }
    });
});
