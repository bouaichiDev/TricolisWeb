<?php

use App\Modules\Customers\Models\Customer;
use App\Modules\DeliveryNotes\Services\DeliveryNotePaths;
use App\Modules\DeliveryNotes\Services\DeliveryNoteRenderContext;
use App\Modules\Documents\Models\Document;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderLine;
use App\Modules\Orders\Models\OrderService;
use App\Modules\Orders\Models\OrderServicePackage;
use App\Modules\Orders\Models\Service;
use App\Modules\Packages\Models\Package;
use App\Modules\Packages\Models\PackageOrderLine;
use App\Modules\Templates\Models\Template;
use App\Modules\Templates\Services\TemplateContext;
use App\Shared\Database\MorphMap;
use Illuminate\Support\Facades\Storage;

/**
 * Le bon de livraison d'un service : résolution, périmètre, PDF.
 *
 * Trois exigences y sont vérifiées, parce que ce sont les trois qu'un BL faux
 * ferait payer au client :
 *
 * 1. **le périmètre** — seuls les colis et articles de *ce* service y figurent ;
 * 2. **la résolution** — le modèle du client l'emporte, celui d'un tiers jamais ;
 * 3. **l'immuabilité du PDF** — retoucher le modèle ne réécrit pas un bon déjà
 *    remis, il n'agit que sur les générations suivantes.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];

    $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
    $this->catalogService = Service::factory()->forOrganization($this->organization)->create(['name' => 'Livraison']);

    $this->order = Order::factory()->forOrganization($this->organization)->create([
        'customer_id' => $this->customer->id,
    ]);

    /** Un service, ses colis, et les articles rangés dedans. */
    $this->makeService = function (string $number, string $packageReference, string $articleName): OrderService {
        $service = OrderService::factory()->forOrder($this->order)->create([
            'service_id' => $this->catalogService->id,
            'service_number' => $number,
        ]);

        $package = Package::factory()->forOrder($this->order)->create(['reference' => $packageReference]);
        $line = OrderLine::factory()->forOrder($this->order)->create(['name' => $articleName, 'quantity' => 5]);

        PackageOrderLine::create([
            'package_id' => $package->id,
            'order_line_id' => $line->id,
            'quantity' => 3,
        ]);

        OrderServicePackage::create([
            'order_service_id' => $service->id,
            'package_id' => $package->id,
            'quantity' => 1,
        ]);

        return $service;
    };

    $this->template = fn (array $attributes = []): Template => Template::factory()->deliveryNote()->create(array_merge([
        'organization_id' => $this->organization->id,
        'code' => 'BL_DEFAULT',
        'name' => 'BL standard',
    ], $attributes));

    $this->preview = fn (OrderService $service, ?string $templateId = null) => $this->actingAs($this->user, 'sanctum')
        ->withHeaders($this->headers)
        ->getJson("/api/v1/orders/{$this->order->id}/services/{$service->id}/delivery-note"
            .($templateId === null ? '' : "?templateId={$templateId}"));

    $this->templates = fn (OrderService $service) => $this->actingAs($this->user, 'sanctum')
        ->withHeaders($this->headers)
        ->getJson("/api/v1/orders/{$this->order->id}/services/{$service->id}/delivery-note/templates");

    $this->generate = fn (OrderService $service, array $payload = []) => $this->actingAs($this->user, 'sanctum')
        ->withHeaders($this->headers)
        ->postJson("/api/v1/orders/{$this->order->id}/services/{$service->id}/delivery-note", $payload);
});

describe('templates', function (): void {
    it('proposes the active applicable templates and names the default', function (): void {
        ($this->template)();
        ($this->template)(['code' => 'BL_CLIENT', 'name' => 'BL client', 'customer_id' => $this->customer->id]);
        ($this->template)(['code' => 'BL_OFF', 'name' => 'BL inactif', 'is_active' => false]);

        $service = ($this->makeService)('SRV-1', 'PKG-1', 'Chaise');
        $response = ($this->templates)($service)->assertOk();

        $codes = array_column($response->json('data'), 'code');

        expect($codes)->toBe(['BL_CLIENT', 'BL_DEFAULT'])
            ->and($response->json('meta.defaultTemplateId'))->toBe($response->json('data.0.id'));
    });

    it('never proposes the template of another customer', function (): void {
        $other = Customer::factory()->create(['organization_id' => $this->organization->id]);
        ($this->template)(['code' => 'BL_OTHER', 'customer_id' => $other->id]);

        $service = ($this->makeService)('SRV-1', 'PKG-1', 'Chaise');

        expect(($this->templates)($service)->assertOk()->json('data'))->toBe([]);
    });
});

describe('preview', function (): void {
    it('renders only the packages and articles of this service', function (): void {
        ($this->template)();

        $first = ($this->makeService)('SRV-1', 'PKG-1', 'Chaise');
        ($this->makeService)('SRV-2', 'PKG-2', 'Table');

        $html = ($this->preview)($first)->assertOk()->json('data.html');

        expect($html)->toContain('PKG-1')
            ->toContain('Chaise')
            ->not->toContain('PKG-2')
            ->not->toContain('Table');
    });

    it('carries the quantity taken by the service, not the ordered one', function (): void {
        ($this->template)();
        $service = ($this->makeService)('SRV-1', 'PKG-1', 'Chaise');

        // La ligne est commandee a 5 et rangee a 3 dans le colis : c'est 3 que
        // le destinataire recoit, et donc 3 que le bon annonce.
        expect(($this->preview)($service)->assertOk()->json('data.html'))->toContain('Chaise x 3.000');
    });

    it('prefers the customer template over the global one', function (): void {
        ($this->template)();
        ($this->template)(['code' => 'BL_CLIENT', 'name' => 'BL client', 'customer_id' => $this->customer->id]);

        $response = ($this->preview)(($this->makeService)('SRV-1', 'PKG-1', 'Chaise'))->assertOk();

        expect($response->json('data.scope'))->toBe('customer')
            ->and($response->json('data.templateCode'))->toBe('BL_CLIENT');
    });

    it('refuses with a configuration error when no template applies', function (): void {
        $response = ($this->preview)(($this->makeService)('SRV-1', 'PKG-1', 'Chaise'));

        $response->assertStatus(409);
        expect($response->json('message'))->toContain('Modèles');
    });

    it('refuses a template that does not apply to this service', function (): void {
        ($this->template)();
        $other = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $foreign = ($this->template)(['code' => 'BL_OTHER', 'customer_id' => $other->id]);

        ($this->preview)(($this->makeService)('SRV-1', 'PKG-1', 'Chaise'), $foreign->id)->assertStatus(409);
    });
});

describe('generation', function (): void {
    beforeEach(function (): void {
        Storage::fake('local');
    });

    it('stores a pdf linked to both the order and the service', function (): void {
        ($this->template)();
        $service = ($this->makeService)('SRV-1', 'PKG-1', 'Chaise');

        $response = ($this->generate)($service)->assertCreated();
        $document = Document::find($response->json('data.id'));

        expect($document->document_type)->toBe('delivery_note')
            ->and($document->mime_type)->toBe('application/pdf')
            ->and($document->reference_number)->toBe("BL-{$this->order->order_number}-SRV-1");

        Storage::disk('local')->assertExists($document->storage_path);

        $this->assertDatabaseHas('document_links', [
            'document_id' => $document->id,
            'entity_type' => MorphMap::ORDER,
            'entity_id' => $this->order->id,
        ]);

        $this->assertDatabaseHas('document_links', [
            'document_id' => $document->id,
            'entity_type' => MorphMap::ORDER_SERVICE,
            'entity_id' => $service->id,
        ]);
    });

    /**
     * Le coeur de la demande : « une modification du modèle doit être appliquée
     * aux nouvelles générations, sans modifier les PDF déjà enregistrés ».
     */
    it('leaves an already generated pdf untouched when the template changes', function (): void {
        $template = ($this->template)();
        $service = ($this->makeService)('SRV-1', 'PKG-1', 'Chaise');

        $first = Document::find(($this->generate)($service)->assertCreated()->json('data.id'));
        $before = Storage::disk('local')->get($first->storage_path);

        $template->update(['body_template' => '<h1>Mise en page revue {{ deliveryNote.number }}</h1>']);

        $second = Document::find(($this->generate)($service)->assertCreated()->json('data.id'));

        expect($second->id)->not->toBe($first->id)
            ->and($second->storage_path)->not->toBe($first->storage_path)
            ->and(Storage::disk('local')->get($first->storage_path))->toBe($before);
    });

    it('refuses generation when no template applies', function (): void {
        ($this->generate)(($this->makeService)('SRV-1', 'PKG-1', 'Chaise'))->assertStatus(409);

        expect(Document::where('document_type', 'delivery_note')->count())->toBe(0);
    });
});

it('answers 404 for a service of another order', function (): void {
    ($this->template)();
    $service = ($this->makeService)('SRV-1', 'PKG-1', 'Chaise');
    $stranger = Order::factory()->forOrganization($this->organization)->create();

    $this->actingAs($this->user, 'sanctum')
        ->withHeaders($this->headers)
        ->getJson("/api/v1/orders/{$stranger->id}/services/{$service->id}/delivery-note")
        ->assertNotFound();
});

/**
 * L'éditeur de modèle propose une liste de chemins ; le rendu refuse tout
 * chemin sans valeur. Les deux doivent donc coïncider — sinon un utilisateur
 * insère la variable qu'on lui a proposée, et son BL échoue à la génération.
 */
it('resolves every path offered to the template editor', function (): void {
    $service = ($this->makeService)('SRV-1', 'PKG-1', 'Chaise');

    $context = app(DeliveryNoteRenderContext::class)->build($service, 'BL-TEST');
    $flat = app(TemplateContext::class)->flatten($context);
    $lists = app(TemplateContext::class)->lists($context);

    $missing = [];

    foreach (DeliveryNotePaths::scalars() as $path) {
        if (! array_key_exists($path, $flat)) {
            $missing[] = $path;
        }
    }

    foreach ([DeliveryNotePaths::PACKAGES, DeliveryNotePaths::ARTICLES] as $path) {
        if (! array_key_exists($path, $lists)) {
            $missing[] = $path;
        }
    }

    $rows = [
        DeliveryNotePaths::PACKAGES => DeliveryNotePaths::packageFields(),
        DeliveryNotePaths::ARTICLES => DeliveryNotePaths::articleFields(),
    ];

    foreach ($rows as $list => $fields) {
        $row = $lists[$list][0] ?? [];

        foreach ($fields as $path) {
            if (! array_key_exists(substr($path, strlen($list) + 1), $row)) {
                $missing[] = $path;
            }
        }
    }

    expect($missing)->toBe([]);
});

it('refuses a delivery note template carrying a channel', function (): void {
    $this->actingAs($this->user, 'sanctum')
        ->withHeaders($this->headers)
        ->postJson('/api/v1/templates', [
            'code' => 'BL_WITH_CHANNEL',
            'name' => 'BL par courriel',
            'templateType' => 'delivery_note',
            'channel' => 'email',
            'bodyTemplate' => '<h1>BL</h1>',
            'language' => 'fr',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('channel');
});
