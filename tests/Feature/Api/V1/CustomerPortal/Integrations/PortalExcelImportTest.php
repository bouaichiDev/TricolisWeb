<?php

use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Models\Order;
use Database\Seeders\CustomerPortalDemoSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CustomerIntegrations;
use Tests\Support\CustomerPortal;

/**
 * Import Excel dans le portail (§25-§31, §54) avec un vrai classeur :
 * prévisualiser, confirmer, voir les erreurs, ne jamais dupliquer.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    Storage::fake('local');
    Storage::fake('public');
    $this->configuration = CustomerIntegrations::import('qoqa', 'xlsx');
    $this->headers = CustomerPortal::headers('admin@qoqa.test');
    $this->xlsx = fn (string $name = 'commandes.xlsx') => new UploadedFile(__DIR__.'/../../../../../Fixtures/integrations/orders.xlsx', $name, null, null, true);
    // Le garde Sanctum garde l'utilisateur d'une requête à l'autre : on l'oublie avant chacune.
    $this->preview = fn (?UploadedFile $file = null, ?string $configurationId = null, ?array $headers = null) => tap($this, fn () => app('auth')->forgetGuards())
        ->withHeaders($headers ?? $this->headers)
        ->post('/api/v1/customer/integrations/imports/preview', ['configurationId' => $configurationId ?? $this->configuration->id, 'file' => $file ?? ($this->xlsx)()], ['Accept' => 'application/json']);
    $this->execute = fn (string $token, ?array $headers = null) => tap($this, fn () => app('auth')->forgetGuards())->withHeaders($headers ?? $this->headers)
        ->postJson('/api/v1/customer/integrations/imports/execute', ['configurationId' => $this->configuration->id, 'token' => $token]);
});

it('prévisualise sans rien créer : colonnes, échantillon, verdict par commande', function (): void {
    $response = ($this->preview)()->assertOk();

    expect($response->json('data.rowCount'))->toBe(3)
        ->and($response->json('data.headers'))->toContain('Commande', 'Quantité')
        ->and($response->json('data.sampleRows.0.Commande'))->toBe('XL-1001')
        ->and($response->json('data.summary'))->toMatchArray(['orderCount' => 2, 'valid' => 1, 'invalid' => 1, 'duplicates' => 0])
        ->and($response->json('data.summary.orders.1.errors'))->toHaveKey('lines.0.quantity')
        ->and(Order::where('source', OrderSource::EXCEL_IMPORT->value)->exists())->toBeFalse();

    // Le fichier est gardé sur le disque privé, sous le jeton — jamais public.
    $token = $response->json('data.token');
    Storage::disk('local')->assertExists("customer-imports/{$this->configuration->customer_id}/{$token}.xlsx");
    expect(Storage::disk('public')->allFiles())->toBe([]);
});

it('crée les commandes valides en EXCEL_IMPORT et rend l’erreur de la ligne invalide', function (): void {
    $token = ($this->preview)()->json('data.token');

    $response = ($this->execute)($token)->assertOk();
    $order = Order::with('lines')->where('external_reference', 'XL-1001')->sole();

    expect($response->json('data'))->toMatchArray(['status' => 'completed', 'created' => 1, 'invalid' => 1])
        ->and($response->json('data.errors.0.reference'))->toBe('XL-1002')
        ->and($response->json('data.errors.0.errors'))->toHaveKey('orders.1.lines.0.quantity')
        ->and($order->source->value)->toBe(OrderSource::EXCEL_IMPORT->value)
        ->and($order->lines->pluck('article_code')->all())->toBe(['TAB-01', 'CHA-02'])
        ->and(Order::where('external_reference', 'XL-1002')->exists())->toBeFalse();

    // Jeton consommé, fichier supprimé.
    ($this->execute)($token)->assertNotFound();
    expect(Storage::disk('local')->allFiles('customer-imports'))->toBe([]);
});

it('signale puis ignore une commande déjà importée', function (): void {
    ($this->execute)(($this->preview)()->json('data.token'));

    $again = ($this->preview)();
    expect($again->json('data.summary.duplicates'))->toBe(1);

    ($this->execute)($again->json('data.token'))->assertJsonPath('data.skipped', 1)->assertJsonPath('data.created', 0);
    expect(Order::where('external_reference', 'XL-1001')->count())->toBe(1);
});

it('refuse un fichier qui n’a que l’extension d’un classeur', function (): void {
    // Un vrai fichier texte nommé .xlsx : le type est lu dans le contenu, pas déduit du nom.
    $path = (string) tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents($path, "Commande;Date\nXL-1;2026-10-05\n");
    $fake = new UploadedFile($path, 'commandes.xlsx', null, null, true);

    ($this->preview)($fake)->assertUnprocessable()->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'format attendu'));
});

it('refuse une extension non prise en charge et un fichier trop gros', function (): void {
    ($this->preview)(UploadedFile::fake()->create('macro.xlsm', 10))->assertUnprocessable()->assertJsonValidationErrors('file');

    config(['customer_integrations.files.max_bytes' => 1024]);
    ($this->preview)()->assertUnprocessable()->assertJsonValidationErrors('file');
});

it('répond 404 sur la configuration ou le jeton d’un autre client', function (): void {
    $ikeaHeaders = CustomerPortal::headers('admin@ikea.test');
    ($this->preview)(null, null, $ikeaHeaders)->assertNotFound();

    $token = ($this->preview)()->json('data.token');
    ($this->execute)($token, $ikeaHeaders)->assertNotFound();

    expect(Order::where('source', OrderSource::EXCEL_IMPORT->value)->exists())->toBeFalse();
});

it('refuse le dépôt sur une intégration désactivée ou automatique', function (array $attributes): void {
    $this->configuration->update($attributes);

    ($this->preview)()->assertUnprocessable();
})->with([
    'désactivée' => [['is_active' => false]],
    'automatique' => [['source_type' => 'sftp']],
]);

it('exige la permission d’importer', function (): void {
    ($this->preview)(null, null, CustomerPortal::headers('viewer@ikea.test'))->assertForbidden();
});

it('met un gros fichier en file et rend son résumé par le jeton', function (): void {
    config(['customer_integrations.uploads.sync_order_limit' => 1]);
    $token = ($this->preview)()->json('data.token');

    ($this->execute)($token)->assertStatus(202)->assertJsonPath('data.status', 'queued');

    $this->withHeaders($this->headers)->getJson("/api/v1/customer/integrations/imports/{$token}")
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.created', 1);
    $this->withHeaders(CustomerPortal::headers('admin@ikea.test'))->getJson("/api/v1/customer/integrations/imports/{$token}")->assertNotFound();
});
