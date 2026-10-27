<?php

use App\Modules\Integrations\Exceptions\PermanentIntegrationFailure;
use App\Modules\Integrations\Services\Import\ImportRunAudit;
use App\Modules\Integrations\Services\Pull\PullIntegrationRunner;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Models\Order;
use Database\Seeders\CustomerPortalDemoSeeder;
use Illuminate\Support\Facades\Log;
use Tests\Support\CustomerIntegrations;

/**
 * Relève FTP/SFTP de fichiers XML (§10-§13, §51) sur un serveur simulé :
 * aucun réseau, aucune connexion réelle.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    CustomerIntegrations::offlineDns();
    $this->configuration = CustomerIntegrations::import('ikea', 'xml');
    $this->disk = CustomerIntegrations::fakeSftp($this->configuration);
    $this->runner = app(PullIntegrationRunner::class);
});

it('relève, importe en XML_IMPORT, puis archive le fichier traité', function (): void {
    $this->disk->put('outbox/orders_001.xml', CustomerIntegrations::xmlFile(
        CustomerIntegrations::xmlOrder('IK-100', [['BILLY-80', 'Billy', '2'], ['PAX-1', 'Pax', '1']]),
        CustomerIntegrations::xmlOrder('IK-101', [['BILLY-80', 'Billy', '4']]),
    ));

    $summary = $this->runner->run($this->configuration);
    $order = Order::with('lines')->where('external_reference', 'IK-100')->sole();

    expect($summary->counts())->toMatchArray(['created' => 2, 'invalid' => 0])
        ->and($order->source->value)->toBe(OrderSource::XML_IMPORT->value)
        ->and($order->lines)->toHaveCount(2);

    $this->disk->assertMissing('outbox/orders_001.xml');
    $this->disk->assertExists('outbox/archive/orders_001.xml');
    $this->assertDatabaseHas('audit_logs', ['action' => ImportRunAudit::ACTION, 'entity_id' => $this->configuration->id]);
});

it('ne duplique rien quand le même fichier est relu', function (): void {
    $xml = CustomerIntegrations::xmlFile(CustomerIntegrations::xmlOrder('IK-200', [['BILLY-80', 'Billy', '2']]));
    $this->disk->put('outbox/orders_002.xml', $xml);
    $this->runner->run($this->configuration);

    // Le client redépose le même fichier (ou l'archivage avait échoué).
    $this->disk->put('outbox/orders_002.xml', $xml);
    $summary = $this->runner->run($this->configuration);

    expect($summary->counts())->toMatchArray(['created' => 0, 'skipped' => 1])
        ->and(Order::where('external_reference', 'IK-200')->count())->toBe(1);
});

it('laisse sur le serveur un fichier dont une commande est refusée', function (): void {
    $this->disk->put('outbox/orders_003.xml', CustomerIntegrations::xmlFile(
        CustomerIntegrations::xmlOrder('IK-300', [['BILLY-80', 'Billy', '1']]),
        CustomerIntegrations::xmlOrder('IK-301', [['BILLY-80', 'Billy', '0']]),
    ));

    $summary = $this->runner->run($this->configuration);

    expect($summary->counts())->toMatchArray(['created' => 1, 'invalid' => 1]);
    $this->disk->assertExists('outbox/orders_003.xml');
    $this->disk->assertMissing('outbox/archive/orders_003.xml');
});

it('écarte un XML mal formé ou piégé (XXE) sans bloquer les autres fichiers', function (): void {
    Log::spy();
    $this->disk->put('outbox/a_broken.xml', '<Orders><Order>');
    $this->disk->put('outbox/b_xxe.xml', '<?xml version="1.0"?><!DOCTYPE r [<!ENTITY x SYSTEM "file:///etc/passwd">]><Orders><Order><OrderNo>&x;</OrderNo></Order></Orders>');
    $this->disk->put('outbox/c_good.xml', CustomerIntegrations::xmlFile(CustomerIntegrations::xmlOrder('IK-400', [['BILLY-80', 'Billy', '1']])));
    $this->disk->put('outbox/notes.txt', 'ignoré : ne correspond pas au motif');

    $summary = $this->runner->run($this->configuration);

    expect($summary->counts()['created'])->toBe(1);
    $this->disk->assertExists('outbox/a_broken.xml');
    $this->disk->assertExists('outbox/b_xxe.xml');
    $this->disk->assertExists('outbox/notes.txt');
    Log::shouldHaveReceived('warning')->with('customer-integration.file_rejected', Mockery::on(
        static fn (array $context): bool => $context['file'] === 'b_xxe.xml' && ! str_contains(json_encode($context), 'mot-de-passe'),
    ));
});

it('supprime ou garde le fichier selon la stratégie configurée', function (string $strategy, bool $kept): void {
    $this->disk = CustomerIntegrations::fakeSftp($this->configuration, ['afterImport' => $strategy]);
    $this->disk->put('outbox/orders_005.xml', CustomerIntegrations::xmlFile(CustomerIntegrations::xmlOrder('IK-500-'.$strategy, [['BILLY-80', 'Billy', '1']])));

    $this->runner->run($this->configuration);

    expect($this->disk->exists('outbox/orders_005.xml'))->toBe($kept);
})->with([['delete', false], ['keep', true]]);

it('refuse une relève non déclarée, vers un hôte interne, ou sans auteur valide', function (array $override, string $message): void {
    CustomerIntegrations::fileConnection($this->configuration, $override === [] ? [] : [
        'host' => 'sftp.partner.example.com', 'directory' => 'outbox', 'actorEmail' => 'admin@tricolis.dev', ...$override,
    ]);

    expect(fn () => $this->runner->run($this->configuration))->toThrow(PermanentIntegrationFailure::class, $message);
})->with([
    'non déclarée' => [[], 'Aucune connexion'],
    'hôte interne' => [['host' => '127.0.0.1'], 'interne'],
    'auteur portail' => [['actorEmail' => 'admin@ikea.test'], 'utilisateur interne actif'],
    'auteur inconnu' => [['actorEmail' => 'personne@example.test'], 'Aucun utilisateur'],
]);

it('ne relit pas au-delà du répertoire configuré', function (): void {
    $this->disk = CustomerIntegrations::fakeSftp($this->configuration, ['directory' => '../../etc']);
    $this->disk->put('etc/passwd.xml', CustomerIntegrations::xmlFile(CustomerIntegrations::xmlOrder('IK-600', [['X', 'X', '1']])));
    $this->disk->put('secret.xml', CustomerIntegrations::xmlFile(CustomerIntegrations::xmlOrder('IK-601', [['X', 'X', '1']])));

    $this->runner->run($this->configuration);

    // `../../etc` devient `etc` : jamais la racine ni un parent.
    expect(Order::where('external_reference', 'IK-601')->exists())->toBeFalse();
});
