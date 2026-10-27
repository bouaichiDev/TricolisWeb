<?php

use App\Modules\Documents\Models\Document;
use Database\Seeders\CustomerPortalDemoSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CustomerPortal;
use Tests\Support\CustomerPortalOrders;

/**
 * Documents et historique : toujours à travers la commande du client.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    Storage::fake('local');
    $this->headers = CustomerPortal::headers('orders@ikea.test');
    $this->order = CustomerPortalOrders::order('ikea');
    $this->uri = "/api/v1/customer/orders/{$this->order->id}";
});

it('uploads, lists and downloads a document of the order', function (): void {
    $id = $this->withHeaders($this->headers)
        ->post("$this->uri/documents", ['file' => UploadedFile::fake()->create('bon.pdf', 20, 'application/pdf')], ['Accept' => 'application/json'])
        ->assertCreated()
        ->json('data.id');

    $this->withHeaders($this->headers)->getJson("$this->uri/documents")->assertOk()->assertJsonPath('data.0.id', $id);
    $this->withHeaders($this->headers)->get("$this->uri/documents/$id/download")->assertOk();
});

it('never serves a document through another order', function (): void {
    $this->withHeaders(CustomerPortal::headers('admin@qoqa.test'))
        ->post('/api/v1/customer/orders/'.CustomerPortalOrders::order('qoqa')->id.'/documents',
            ['file' => UploadedFile::fake()->create('qoqa.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
        ->assertCreated();
    $foreign = Document::where('file_name', 'qoqa.pdf')->firstOrFail();

    $this->withHeaders($this->headers)->getJson("$this->uri/documents/{$foreign->id}/download")->assertNotFound();
});

it('shows a limited functional history, without raw values', function (): void {
    $this->withHeaders($this->headers)->postJson("$this->uri/cancel", ['reason' => 'Erreur']);

    $history = $this->withHeaders(CustomerPortal::headers('viewer@ikea.test'))->getJson("$this->uri/history")->assertOk()->json('data');

    expect(collect($history)->pluck('type'))->toContain('created', 'status_changed')
        ->and($history[0])->not->toHaveKeys(['oldValues', 'newValues', 'ipAddress'])
        ->and($history[0]['status']['to'])->toBe('Annulée');
});
