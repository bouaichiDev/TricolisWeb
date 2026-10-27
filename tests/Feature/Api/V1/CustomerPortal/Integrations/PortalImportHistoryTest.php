<?php

use App\Modules\Integrations\Models\CustomerImportRun;
use Database\Seeders\CustomerPortalDemoSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CustomerIntegrations;
use Tests\Support\CustomerPortal;

/**
 * L'historique des imports dans le portail (décision Phase 6 levée le
 * 2026-10-10) : chaque dépôt laisse une trace durable, avec les commandes
 * refusées et leurs messages — jamais le contenu des lignes.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    Storage::fake('local');
    $this->configuration = CustomerIntegrations::import('qoqa', 'xlsx');
    $this->headers = CustomerPortal::headers('admin@qoqa.test');
    $this->import = function (): void {
        $file = new UploadedFile(__DIR__.'/../../../../../Fixtures/integrations/orders.xlsx', 'commandes.xlsx', null, null, true);
        $token = $this->withHeaders($this->headers)
            ->post('/api/v1/customer/integrations/imports/preview', ['configurationId' => $this->configuration->id, 'file' => $file], ['Accept' => 'application/json'])
            ->json('data.token');
        app('auth')->forgetGuards();
        $this->withHeaders($this->headers)->postJson('/api/v1/customer/integrations/imports/execute', ['configurationId' => $this->configuration->id, 'token' => $token])->assertOk();
        app('auth')->forgetGuards();
    };
});

it('garde chaque dépôt : bilan, fichier, auteur, commandes refusées', function (): void {
    ($this->import)();

    $run = CustomerImportRun::sole();

    expect($run->status)->toBe(CustomerImportRun::COMPLETED_WITH_ERRORS)
        ->and($run->channel)->toBe('portal')
        ->and($run->file_name)->toBe('commandes.xlsx')
        ->and($run->created_count)->toBe(1)
        ->and($run->invalid_count)->toBe(1)
        ->and($run->triggered_by)->toBe(CustomerPortal::member('admin@qoqa.test')->user_id)
        ->and($run->errors)->toHaveCount(1)
        ->and($run->errors[0]['reference'])->toBe('XL-1002')
        ->and($run->errors[0]['errors'])->toHaveKey('orders.1.lines.0.quantity')
        ->and($run->started_at)->not->toBeNull();
});

it('liste l’historique sans le détail, et le rend sur la fiche', function (): void {
    ($this->import)();

    $list = $this->withHeaders($this->headers)->getJson('/api/v1/customer/integrations/import-runs')->assertOk();
    expect($list->json('meta.total'))->toBe(1)
        ->and($list->json('data.0.configuration.name'))->toBe($this->configuration->name)
        ->and($list->json('data.0.counts'))->toMatchArray(['created' => 1, 'invalid' => 1])
        ->and($list->json('data.0'))->not->toHaveKey('errors');

    $id = $list->json('data.0.id');
    $detail = $this->withHeaders($this->headers)->getJson("/api/v1/customer/integrations/import-runs/{$id}")->assertOk();
    expect($detail->json('data.errors.0.errors'))->toHaveKey('orders.1.lines.0.quantity')
        ->and($detail->json('data.triggeredBy.name'))->not->toBeEmpty();
});

it('tait le nom de l’employé du transporteur qui a importé pour le client', function (): void {
    CustomerImportRun::create([
        'customer_id' => $this->configuration->customer_id, 'configuration_id' => $this->configuration->id, 'channel' => 'internal',
        'source' => 'excel_import', 'status' => CustomerImportRun::COMPLETED, 'triggered_by' => authUser()->id, 'finished_at' => now(),
    ]);

    $response = $this->withHeaders($this->headers)->getJson('/api/v1/customer/integrations/import-runs')->assertOk();

    expect($response->json('data.0.channel'))->toBe('internal')
        ->and($response->json('data.0.triggeredBy'))->toBeNull()
        ->and($response->getContent())->not->toContain(authUser()->last_name);
});

it('filtre par configuration et statut, et refuse un client imposé', function (): void {
    ($this->import)();

    $this->withHeaders($this->headers)->getJson('/api/v1/customer/integrations/import-runs?status=failed')->assertOk()->assertJsonPath('meta.total', 0);
    $this->withHeaders($this->headers)->getJson("/api/v1/customer/integrations/import-runs?configurationId={$this->configuration->id}")->assertOk()->assertJsonPath('meta.total', 1);
    $this->withHeaders($this->headers)->getJson('/api/v1/customer/integrations/import-runs?customerId='.CustomerPortal::customer('ikea')->id)->assertUnprocessable();
});

it('exige de voir les intégrations', function (): void {
    $this->withHeaders(CustomerPortal::headers('viewer@ikea.test'))->getJson('/api/v1/customer/integrations/import-runs')->assertForbidden();
});
