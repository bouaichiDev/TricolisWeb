<?php

use App\Modules\Identity\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Platform\Mail\AccessRequestSubmittedMail;
use App\Modules\Platform\Models\AccessRequest;
use App\Shared\Enums\AccessRequestStatus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

/**
 * Les demandes d'accès déposées depuis l'écran de connexion.
 *
 * Tout tient dans une asymétrie : **déposer est public, trancher ne l'est
 * pas**. Le formulaire est rempli par quelqu'un qui n'a pas de compte — c'est
 * bien pour cela qu'il en demande un — et il ne crée rien. L'organisation et
 * son administrateur ne naissent qu'à l'acceptation, par la plateforme.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();

    Mail::fake();
    Notification::fake();
});

/** @return array<string, string> */
function accessRequestPayload(array $overrides = []): array
{
    return [
        'companyName' => 'Transports Atlas',
        'contactName' => 'Sara Bennani',
        'email' => 'sara@atlas.example',
        'phone' => '+212 600 000 000',
        ...$overrides,
    ];
}

describe('submitting', function (): void {
    it('records the request without creating anything', function (): void {
        $this->postJson('/api/v1/access-requests', accessRequestPayload())
            ->assertCreated();

        $request = AccessRequest::where('email', 'sara@atlas.example')->firstOrFail();

        expect($request->status)->toBe(AccessRequestStatus::PENDING)
            ->and($request->organization_id)->toBeNull()
            ->and(User::where('email', 'sara@atlas.example')->exists())->toBeFalse()
            ->and(Organization::where('name', 'Transports Atlas')->exists())->toBeFalse();
    });

    it('warns the platform administrators', function (): void {
        makePlatformAdmin($this->user);

        $this->postJson('/api/v1/access-requests', accessRequestPayload())->assertCreated();

        Mail::assertSent(AccessRequestSubmittedMail::class);
    });

    /**
     * Un formulaire renvoyé trois fois par impatience remplirait l'écran de la
     * plateforme de doublons, dont deux seraient refusés sans qu'on sache
     * pourquoi.
     */
    it('refuses a second request while the first is pending', function (): void {
        $this->postJson('/api/v1/access-requests', accessRequestPayload())->assertCreated();

        $this->postJson('/api/v1/access-requests', accessRequestPayload())
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    });

    it('requires a phone number', function (): void {
        $this->postJson('/api/v1/access-requests', accessRequestPayload(['phone' => '']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('phone');
    });
});

describe('reading', function (): void {
    it('refuses an anonymous caller', function (): void {
        $this->getJson('/api/v1/access-requests')->assertUnauthorized();
    });

    /**
     * Le propriétaire d'un organisme détient pourtant tout chez lui : une
     * demande d'accès ne relève d'aucune organisation, et son examen ne se
     * délègue pas.
     */
    it('refuses an organization owner', function (): void {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/access-requests')
            ->assertForbidden();
    });

    it('lists them for a platform administrator', function (): void {
        $this->postJson('/api/v1/access-requests', accessRequestPayload())->assertCreated();

        $this->actingAs(makePlatformAdmin($this->user), 'sanctum')
            ->getJson('/api/v1/access-requests?status=pending')
            ->assertOk()
            ->assertJsonPath('data.0.companyName', 'Transports Atlas')
            ->assertJsonPath('data.0.phone', '+212 600 000 000');
    });
});

describe('deciding', function (): void {
    beforeEach(function (): void {
        $this->postJson('/api/v1/access-requests', accessRequestPayload())->assertCreated();
        $this->request = AccessRequest::where('email', 'sara@atlas.example')->firstOrFail();

        // L'élévation n'est pas faite ici : le dernier cas vérifie justement ce
        // que voit un compte qui ne l'a pas, et le compte semé est le même.
        $this->admin = fn (): User => makePlatformAdmin($this->user);
    });

    it('creates the organization and its administrator on approval', function (): void {
        $this->actingAs(($this->admin)(), 'sanctum')
            ->postJson("/api/v1/access-requests/{$this->request->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $created = User::where('email', 'sara@atlas.example')->firstOrFail();
        $organization = Organization::where('name', 'Transports Atlas')->firstOrFail();

        expect($created->first_name)->toBe('Sara')
            ->and($created->last_name)->toBe('Bennani')
            ->and($this->request->refresh()->organization_id)->toBe($organization->id)
            ->and($created->organizationUsers()->where('organization_id', $organization->id)->exists())->toBeTrue();
    });

    /**
     * Jamais de mot de passe en clair : le compte naît avec un secret que
     * personne ne lit, et le demandeur reçoit le lien qui lui fera choisir le
     * sien.
     */
    it('sends the new administrator a link rather than a password', function (): void {
        $this->actingAs(($this->admin)(), 'sanctum')
            ->postJson("/api/v1/access-requests/{$this->request->id}/approve")
            ->assertOk();

        Notification::assertSentTo(
            User::where('email', 'sara@atlas.example')->firstOrFail(),
            Illuminate\Auth\Notifications\ResetPassword::class,
        );
    });

    it('refuses to decide twice', function (): void {
        $this->actingAs(($this->admin)(), 'sanctum')
            ->postJson("/api/v1/access-requests/{$this->request->id}/approve")
            ->assertOk();

        $this->actingAs(($this->admin)(), 'sanctum')
            ->postJson("/api/v1/access-requests/{$this->request->id}/approve")
            ->assertStatus(422);
    });

    it('creates nothing when the request is rejected', function (): void {
        $this->actingAs(($this->admin)(), 'sanctum')
            ->postJson("/api/v1/access-requests/{$this->request->id}/reject", ['note' => 'Société inconnue.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.decisionNote', 'Société inconnue.');

        expect(User::where('email', 'sara@atlas.example')->exists())->toBeFalse()
            ->and(Organization::where('name', 'Transports Atlas')->exists())->toBeFalse();
    });

    it('refuses an organization owner', function (): void {
        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/access-requests/{$this->request->id}/reject")
            ->assertForbidden();
    });
});
