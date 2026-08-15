<?php

use App\Modules\Communications\Models\CommunicationAttachment;
use App\Modules\Communications\Models\OrderCommunication;
use App\Modules\Documents\Models\Document;
use App\Modules\Orders\Models\Order;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];

    $this->order = Order::factory()->forOrganization($this->organization)->create();
    $this->communication = OrderCommunication::factory()->forOrder($this->order)->create();
    $this->document = Document::factory()->forOrganization($this->organization)->create([
        'file_name' => 'bon-de-livraison.pdf',
        'mime_type' => 'application/pdf',
    ]);

    $this->attach = fn (array $payload = []) => $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->postJson(
            "/api/v1/order-communications/{$this->communication->id}/attachments",
            array_merge(['documentId' => $this->document->id], $payload),
        );
});

describe('attachment creation', function (): void {
    it('attaches a document and freezes its name and type', function (): void {
        ($this->attach)()
            ->assertCreated()
            ->assertJsonPath('data.documentId', $this->document->id)
            ->assertJsonPath('data.fileNameSnapshot', 'bon-de-livraison.pdf')
            ->assertJsonPath('data.mimeTypeSnapshot', 'application/pdf');
    });

    it('keeps the snapshot when the document is renamed afterwards', function (): void {
        $id = ($this->attach)()->assertCreated()->json('data.id');

        $this->document->update(['file_name' => 'renomme.pdf', 'mime_type' => 'image/png']);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/order-communications/{$this->communication->id}/attachments/{$id}")
            ->assertOk()
            ->assertJsonPath('data.fileNameSnapshot', 'bon-de-livraison.pdf')
            ->assertJsonPath('data.mimeTypeSnapshot', 'application/pdf')
            ->assertJsonPath('data.document.fileName', 'renomme.pdf');
    });

    it('refuses a document from another organization', function (): void {
        $foreign = Document::factory()->create();

        ($this->attach)(['documentId' => $foreign->id])
            ->assertStatus(422)->assertJsonValidationErrors('documentId');
    });

    it('refuses the same document twice', function (): void {
        ($this->attach)()->assertCreated();
        ($this->attach)()->assertStatus(409);

        expect($this->communication->attachments()->count())->toBe(1);
    });

    it('refuses to attach once the communication has been sent', function (): void {
        $sent = OrderCommunication::factory()->forOrder($this->order)->sent()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/order-communications/{$sent->id}/attachments", ['documentId' => $this->document->id])
            ->assertStatus(409);
    });
});

describe('attachment listing, deletion and scope', function (): void {
    it('lists the attachments of a communication', function (): void {
        CommunicationAttachment::factory(2)->forCommunication($this->communication)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/order-communications/{$this->communication->id}/attachments")
            ->assertOk()->assertJsonCount(2, 'data');
    });

    it('removes an attachment before sending without touching the document', function (): void {
        $id = ($this->attach)()->assertCreated()->json('data.id');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/order-communications/{$this->communication->id}/attachments/{$id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('communication_attachments', ['id' => $id]);
        $this->assertDatabaseHas('documents', ['id' => $this->document->id]);
    });

    it('refuses to remove an attachment once the communication has been sent', function (): void {
        $sent = OrderCommunication::factory()->forOrder($this->order)->sent()->create();
        $attachment = CommunicationAttachment::factory()->forCommunication($sent)->forDocument($this->document)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/order-communications/{$sent->id}/attachments/{$attachment->id}")
            ->assertStatus(409);
    });

    it('deletes the attachments along with a deleted draft', function (): void {
        ($this->attach)()->assertCreated();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/order-communications/{$this->communication->id}")->assertNoContent();

        $this->assertDatabaseMissing('communication_attachments', ['communication_id' => $this->communication->id]);
        $this->assertDatabaseHas('documents', ['id' => $this->document->id]);
    });

    it('hides an attachment belonging to another communication', function (): void {
        $other = OrderCommunication::factory()->forOrder($this->order)->create();
        $attachment = CommunicationAttachment::factory()->forCommunication($other)->forDocument($this->document)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/order-communications/{$this->communication->id}/attachments/{$attachment->id}")
            ->assertNotFound();
    });

    it('hides the attachments of another organization', function (): void {
        $foreign = OrderCommunication::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/order-communications/{$foreign->id}/attachments")->assertNotFound();
    });

    it('journals the creation and the deletion', function (): void {
        $id = ($this->attach)()->assertCreated()->json('data.id');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/order-communications/{$this->communication->id}/attachments/{$id}")
            ->assertNoContent();

        foreach (['communication_attachment.created', 'communication_attachment.deleted'] as $action) {
            $this->assertDatabaseHas('audit_logs', [
                'action' => $action, 'entity_type' => 'communication_attachment', 'entity_id' => $id,
            ]);
        }
    });

    it('offers no patch route and no updated_at column', function (): void {
        $id = ($this->attach)()->assertCreated()->json('data.id');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/order-communications/{$this->communication->id}/attachments/{$id}", [
                'fileNameSnapshot' => 'usurpation.pdf',
            ])
            ->assertStatus(405);

        expect(Schema::getColumnListing('communication_attachments'))->not->toContain('updated_at');
    });
});
