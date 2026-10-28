<?php

use App\Modules\Communications\Enums\CommunicationStatus;
use App\Modules\Communications\Models\OrderCommunication;
use App\Modules\Documents\Models\Document;
use App\Modules\Drivers\Models\Driver;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;
use App\Modules\ProviderPortal\Support\ProviderRouteSecurity;
use App\Modules\ProviderSettlements\Models\ProviderSettlement;
use App\Modules\Tours\Enums\TourStatus;
use App\Modules\Tours\Enums\TourStopStatus;
use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Models\TourStop;
use App\Modules\Tours\Models\TourStopService;
use Database\Seeders\DemoProviderPortalSeeder;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ProviderPortal;

/**
 * IDOR entre fournisseurs (Phase 7) : Beta, avec tous les droits, essaie
 * chaque route paramétrée avec les identifiants **réels** d'Alpha. La réponse
 * doit être exactement celle d'un identifiant qui n'existe pas — ni accès, ni
 * oracle d'existence (même statut, même message, mêmes erreurs).
 */
beforeEach(function (): void {
    Storage::fake('local');
    $this->seed();
    $this->seed(DemoProviderPortalSeeder::class);
    $alpha = ProviderPortal::provider('transports-alpha');
    $org = $alpha->organization;
    $tour = Tour::factory()->forOrganization($org)->create(['provider_id' => $alpha->id, 'status' => TourStatus::IN_PROGRESS]);
    $stop = TourStop::factory()->forTour($tour)->create(['status' => TourStopStatus::ARRIVED]);
    $order = Order::factory()->create(['organization_id' => $org->id]);
    TourStopService::factory()->forStop($stop)->create(['order_service_id' => OrderService::factory()->create(['order_id' => $order->id])->id, 'confirmed_at' => now()]);
    Storage::disk('local')->put('documents/alpha', 'alpha');
    $document = Document::create(['organization_id' => $org->id, 'document_type' => 'instructions', 'status' => 'active', 'file_name' => 'a.pdf', 'storage_path' => 'documents/alpha', 'mime_type' => 'application/pdf', 'size' => 5]);
    $document->links()->create(['entity_type' => 'tour', 'entity_id' => $tour->id]);
    $settlement = ProviderSettlement::factory()->forProvider($alpha)->create(['status' => 'closed']);
    $communication = OrderCommunication::factory()->create(['order_id' => $order->id, 'recipient_email' => 'admin@transports-alpha.test', 'status' => CommunicationStatus::SENT, 'sent_at' => now()]);

    $this->alphaIds = [
        'tour' => $tour->id, 'stop' => $stop->id, 'document' => $document->id, 'settlement' => $settlement->id, 'communication' => $communication->id,
        'driver' => Driver::factory()->forProvider($alpha)->create()->id, 'vehicle' => Vehicle::factory()->forProvider($alpha)->create()->id,
    ];
    $this->beta = ProviderPortal::headers('admin@transports-beta.test');
});

it('répond à Beta comme si les ressources d’Alpha n’existaient pas', function (): void {
    $routes = array_filter(app(ProviderRouteSecurity::class)->rows(), static fn (array $row): bool => $row['context'] === 'provider.portal' && str_contains($row['uri'], '{'));
    $fill = static fn (string $uri, callable $id): string => '/'.preg_replace_callback('/\{(\w+)\}/', static fn (array $m): string => $id($m[1]), $uri);
    $leaks = [];

    foreach ($routes as $route) {
        $method = explode('|', $route['method'])[0];
        $unknown = array_diff(array_map(static fn (array $m): string => $m[1], (preg_match_all('/\{(\w+)\}/', $route['uri'], $m, PREG_SET_ORDER) ? $m : [])), array_keys($this->alphaIds));
        expect($unknown)->toBe([], "paramètre sans identifiant d'Alpha : {$route['uri']}");

        $real = $this->withHeaders($this->beta)->json($method, $fill($route['uri'], fn (string $name): string => $this->alphaIds[$name]));
        $none = $this->withHeaders($this->beta)->json($method, $fill($route['uri'], static fn (): string => '01JZZZZZZZZZZZZZZZZZZZZZZZ'));

        // Statut, message et code : ce que voit l'appelant. La pile d'appels,
        // ajoutée en debug seulement, diffère d'une requête à l'autre.
        $seen = static fn ($response): array => [$response->status(), $response->json('message'), $response->json('code'), $response->json('errors')];

        if ($seen($real) !== $seen($none) || in_array($real->status(), [200, 201, 204], true)) {
            $leaks[] = "{$route['method']} {$route['uri']} → {$real->status()} (inexistant : {$none->status()})";
        }
    }

    expect(count($routes))->toBeGreaterThan(20)->and($leaks)->toBe([]);
});
