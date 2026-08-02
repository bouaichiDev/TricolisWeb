<?php

use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Models\EntityContact;

beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
});

describe('contacts', function (): void {
    it('lists contacts for the active organization', function (): void {
        $contact = Contact::factory()->create();
        EntityContact::create([
            'organization_id' => $this->organization->id,
            'contact_id' => $contact->id,
            'entity_type' => 'organization',
            'entity_id' => $this->organization->id,
        ]);

        Contact::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeaders($this->headers)
            ->getJson('/api/v1/contacts');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('creates a contact linked to the active organization', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeaders($this->headers)
            ->postJson('/api/v1/contacts', [
                'firstName' => 'Jean',
                'lastName' => 'Dupont',
                'email' => 'jean.dupont@example.com',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.firstName', 'Jean')
            ->assertJsonPath('data.lastName', 'Dupont');
    });

    it('prevents creating a contact without organization header', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/contacts', [
                'firstName' => 'Jean',
                'lastName' => 'Dupont',
            ]);

        $response->assertForbidden();
    });

    it('rejects invalid contact payload', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeaders($this->headers)
            ->postJson('/api/v1/contacts', [
                'email' => 'not-an-email',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['firstName', 'lastName', 'email']);
    });

    it('shows a contact belonging to the active organization', function (): void {
        $contact = Contact::factory()->create();
        EntityContact::create([
            'organization_id' => $this->organization->id,
            'contact_id' => $contact->id,
            'entity_type' => 'organization',
            'entity_id' => $this->organization->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeaders($this->headers)
            ->getJson("/api/v1/contacts/{$contact->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $contact->id);
    });

    it('prevents showing a contact from another organization', function (): void {
        $contact = Contact::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeaders($this->headers)
            ->getJson("/api/v1/contacts/{$contact->id}");

        $response->assertForbidden();
    });

    it('updates a contact', function (): void {
        $contact = Contact::factory()->create();
        EntityContact::create([
            'organization_id' => $this->organization->id,
            'contact_id' => $contact->id,
            'entity_type' => 'organization',
            'entity_id' => $this->organization->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeaders($this->headers)
            ->patchJson("/api/v1/contacts/{$contact->id}", [
                'firstName' => 'Marie',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.firstName', 'Marie');
    });

    it('deletes a contact', function (): void {
        $contact = Contact::factory()->create();
        EntityContact::create([
            'organization_id' => $this->organization->id,
            'contact_id' => $contact->id,
            'entity_type' => 'organization',
            'entity_id' => $this->organization->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeaders($this->headers)
            ->deleteJson("/api/v1/contacts/{$contact->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('contacts', ['id' => $contact->id]);
    });
});
