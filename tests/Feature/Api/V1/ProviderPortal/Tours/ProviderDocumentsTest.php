<?php

use App\Modules\Documents\Models\Document;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;
use App\Modules\Organizations\Models\Organization;
use App\Modules\ProofOfDelivery\Models\ProofOfDelivery;
use App\Modules\Tours\Enums\TourStatus;
use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Models\TourStop;
use App\Modules\Tours\Models\TourStopService;
use Database\Seeders\DemoProviderPortalSeeder;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ProviderPortal;

/**
 * Documents d'exécution : ceux de la tournée, des services confirmés de ses
 * arrêts et des preuves de livraison de ses arrêts — jamais un document de la
 * seule commande (facture, BL d'autres services) ni d'une autre tournée.
 */
beforeEach(function (): void {
    Storage::fake('local');
    $this->seed();
    $this->seed(DemoProviderPortalSeeder::class);
    $this->alpha = ProviderPortal::provider('transports-alpha');
    $org = $this->alpha->organization;
    $this->doc = function (string $name, string $type, string $alias, string $entityId, ?string $orgId = null): Document {
        Storage::disk('local')->put("documents/{$name}", "contenu {$name}");
        $document = Document::create([
            'organization_id' => $orgId ?? $this->alpha->organization_id, 'document_type' => $type, 'status' => 'active',
            'file_name' => "{$name}.pdf", 'storage_path' => "documents/{$name}", 'mime_type' => 'application/pdf', 'size' => 10,
        ]);
        $document->links()->create(['entity_type' => $alias, 'entity_id' => $entityId]);

        return $document;
    };
    $this->tour = Tour::factory()->forOrganization($org)->create(['provider_id' => $this->alpha->id, 'status' => TourStatus::IN_PROGRESS]);
    $stop = TourStop::factory()->forTour($this->tour)->create();
    $order = Order::factory()->create(['organization_id' => $org->id]);
    $service = OrderService::factory()->create(['order_id' => $order->id]);
    $pending = OrderService::factory()->create(['order_id' => $order->id]);
    TourStopService::factory()->forStop($stop)->create(['order_service_id' => $service->id, 'confirmed_at' => now()]);
    TourStopService::factory()->forStop($stop)->create(['order_service_id' => $pending->id, 'confirmed_at' => null]);
    $proof = ProofOfDelivery::create(['order_id' => $order->id, 'order_service_id' => $service->id, 'tour_stop_id' => $stop->id, 'recipient_name' => 'R', 'delivered_at' => now()]);
    // Une tournée sœur d'Alpha : ses documents ne figurent pas dans la liste de
    // celle-ci. Une tournée de Beta : ses documents ne s'ouvrent jamais.
    $sibling = Tour::factory()->forOrganization($org)->create(['provider_id' => $this->alpha->id, 'status' => TourStatus::IN_PROGRESS]);
    $theirs = Tour::factory()->forOrganization($org)->create(['provider_id' => ProviderPortal::provider('transports-beta')->id, 'status' => TourStatus::IN_PROGRESS]);

    $this->allowed = [
        ($this->doc)('consignes', 'instructions', 'tour', $this->tour->id),
        ($this->doc)('bl-service', 'delivery_note', 'order_service', $service->id),
        ($this->doc)('preuve', 'proof_photo', 'proof_of_delivery', $proof->id),
    ];
    $this->denied = [
        'facture' => ($this->doc)('facture', 'invoice', 'order', $order->id),
        'en composition' => ($this->doc)('pending', 'delivery_note', 'order_service', $pending->id),
        'autre fournisseur' => ($this->doc)('autre', 'instructions', 'tour', $theirs->id),
        'autre organisation' => ($this->doc)('ailleurs', 'instructions', 'tour', $this->tour->id, Organization::factory()->create()->id),
    ];
    $this->sibling = ($this->doc)('soeur', 'instructions', 'tour', $sibling->id);
    $this->admin = ProviderPortal::headers('admin@transports-alpha.test');
});

it('ne liste que les documents d’exécution de la tournée', function (): void {
    $ids = collect($this->withHeaders($this->admin)->getJson("/api/v1/provider/tours/{$this->tour->id}/documents")->assertOk()->json('data'))->pluck('id');

    expect($ids->sort()->values()->all())->toBe(collect($this->allowed)->pluck('id')->sort()->values()->all())
        ->and($ids)->not->toContain($this->sibling->id);

    $this->withHeaders($this->admin)->getJson('/api/v1/provider/tours/'.Tour::where('provider_id', ProviderPortal::provider('transports-beta')->id)->latest('id')->value('id').'/documents')->assertNotFound();
});

it('télécharge un document autorisé en flux protégé', function (): void {
    $response = $this->withHeaders($this->admin)->get("/api/v1/provider/documents/{$this->allowed[0]->id}")->assertOk();

    expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->streamedContent())->toBe('contenu consignes');
});

it('répond 404 pour tout autre document', function (string $case): void {
    $this->withHeaders($this->admin)->get("/api/v1/provider/documents/{$this->denied[$case]->id}")->assertNotFound();
})->with(['facture', 'en composition', 'autre fournisseur', 'autre organisation']);
