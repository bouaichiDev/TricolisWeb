<?php

use App\Modules\Communications\Enums\CommunicationChannel;
use App\Modules\Communications\Enums\CommunicationStatus;
use App\Modules\Communications\Models\OrderCommunication;
use App\Modules\Orders\Models\Order;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationUser;

/**
 * La cloche du bandeau supérieur.
 *
 * Elle ne s'appuie sur **aucune table nouvelle** : le domaine porte les deux
 * notions depuis la Phase 9 — le canal `internal_notification`, qui n'appelle
 * aucun tiers, et les envois externes qui, eux, peuvent échouer.
 *
 * Ce que ces tests tiennent est l'asymétrie entre les deux moitiés : les
 * internes m'appartiennent, les externes appartiennent à l'organisation.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->order = Order::factory()->forOrganization($this->organization)->create();
});

function notification(array $overrides = []): OrderCommunication
{
    return OrderCommunication::create([
        'organization_id' => test()->organization->id,
        'order_id' => test()->order->id,
        'channel' => CommunicationChannel::INTERNAL_NOTIFICATION->value,
        'communication_type' => 'order_cancelled',
        'recipient_role' => 'internal_user',
        'recipient_name' => 'Admin Tricolis',
        'recipient_email' => test()->user->email,
        'subject' => 'Une commande vous attend',
        'body' => 'Corps du message',
        'status' => CommunicationStatus::SENT->value,
        ...$overrides,
    ]);
}

describe('the feed', function (): void {
    it('carries what is addressed to me', function (): void {
        notification();

        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/notifications')
            ->assertOk();

        expect($response->json('data.unread'))->toBe(1)
            ->and($response->json('data.internal.0.title'))->toBe('Une commande vous attend');
    });

    /**
     * Le destinataire se reconnaît à son adresse, faute de `recipient_user_id`.
     * Une notification écrite pour un collègue ne doit pas m'être servie.
     */
    it('leaves a colleague notification alone', function (): void {
        notification(['recipient_email' => 'quelquun.dautre@tricolis.dev']);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('data.unread', 0)
            ->assertJsonCount(0, 'data.internal');
    });

    /**
     * Un envoi réussi n'appelle aucune action, et noierait les échecs qui, eux,
     * en appellent une.
     */
    it('shows external sends only when they failed', function (): void {
        notification([
            'channel' => CommunicationChannel::EMAIL->value,
            'recipient_role' => 'customer',
            'recipient_email' => 'client@exemple.test',
            'status' => CommunicationStatus::SENT->value,
        ]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/notifications')->assertOk()->assertJsonCount(0, 'data.external');

        notification([
            'channel' => CommunicationChannel::EMAIL->value,
            'recipient_role' => 'customer',
            'recipient_email' => 'client@exemple.test',
            'status' => CommunicationStatus::FAILED->value,
        ]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/notifications')->assertOk()->assertJsonCount(1, 'data.external');
    });

    /**
     * La cloche est rendue sur chaque page, y compris pour un compte qui n'agit
     * dans aucune organisation. Lui rendre une erreur là où la réponse juste est
     * « rien à signaler » ferait clignoter le bandeau d'un administrateur
     * plateforme.
     */
    it('answers without an organization header', function (): void {
        notification();

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('data.unread', 0)
            ->assertJsonCount(0, 'data.internal');
    });

    it('never carries the message body', function (): void {
        notification();

        $body = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/notifications')->assertOk()->getContent();

        expect($body)->not->toContain('Corps du message');
    });

    /**
     * L'organisation d'à côté n'a rien à voir ici, même pour un compte qui
     * appartient aux deux.
     */
    it('keeps one organization out of another', function (): void {
        $other = Organization::factory()->create();
        OrganizationUser::create([
            'organization_id' => $other->id,
            'user_id' => $this->user->id,
            'is_owner' => false,
            'is_primary' => false,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        notification();

        $this->actingAs($this->user, 'sanctum')
            ->withHeaders(['X-Organization-Id' => $other->id])
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(0, 'data.internal');
    });
});

describe('marking as read', function (): void {
    it('marks mine, and drops the count', function (): void {
        $mine = notification();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/notifications/{$mine->id}/read")
            ->assertOk()
            ->assertJsonPath('data.unread', 0);

        expect($mine->fresh()->read_at)->not->toBeNull();
    });

    /**
     * `read_at` n'est écrit qu'une fois : le relire ne doit pas déplacer la date
     * à laquelle on l'a vue pour la première fois.
     */
    it('keeps the first date when read again', function (): void {
        // `read_at` n'est pas dans les attributs assignables du modele : il est
        // ecrit par la mecanique d'envoi, pas par un formulaire.
        $mine = notification();
        $mine->forceFill(['read_at' => now()->subDay()])->save();

        $first = $mine->fresh()->read_at;

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/notifications/{$mine->id}/read")->assertOk();

        expect($mine->fresh()->read_at->toIso8601String())->toBe($first->toIso8601String());
    });

    it('refuses a colleague notification', function (): void {
        $theirs = notification(['recipient_email' => 'quelquun.dautre@tricolis.dev']);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/notifications/{$theirs->id}/read")
            ->assertForbidden();
    });

    /**
     * Sur un envoi externe, `read_at` porte l'accusé de lecture du destinataire
     * réel : l'écraser falsifierait la trace de l'envoi.
     */
    it('refuses an external send, whatever its recipient', function (): void {
        $external = notification([
            'channel' => CommunicationChannel::EMAIL->value,
            'recipient_role' => 'customer',
        ]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/notifications/{$external->id}/read")
            ->assertForbidden();
    });

    it('marks every one of mine at once, and none of theirs', function (): void {
        notification();
        notification();
        $theirs = notification(['recipient_email' => 'quelquun.dautre@tricolis.dev']);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('data.unread', 0);

        expect($theirs->fresh()->read_at)->toBeNull();
    });
});
