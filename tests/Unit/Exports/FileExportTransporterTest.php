<?php

use App\Modules\Exports\Models\CustomerExportConfiguration;
use App\Modules\Exports\Services\RemoteTargetGuard;
use App\Modules\Exports\Services\Transports\FileExportTransporter;
use App\Shared\Support\Secret;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Le dépôt d'une facture sur un serveur FTP ou SFTP.
 *
 * Aucun serveur n'est joignable depuis la suite, et il ne le faut pas : ce
 * qu'on vérifie ici est le comportement du transporteur — où il écrit, ce qu'il
 * refuse, et ce qu'il dit quand il échoue. Le disque est donc un double, monté
 * là où le service construirait le sien.
 *
 * Le conteneur est nécessaire pour déchiffrer le mot de passe : le §124 veut
 * qu'il ne vive nulle part en clair, pas même dans un jeu de test.
 */
uses(TestCase::class);

beforeEach(function (): void {
    $this->transporter = new FileExportTransporter(new RemoteTargetGuard);

    $this->configuration = fn (array $o = []): CustomerExportConfiguration => new CustomerExportConfiguration(
        array_merge([
            'transport' => 'sftp',
            'host' => 'sftp.client.example',
            'port' => 22,
            'username' => 'tricolis',
            'encrypted_password' => Secret::encrypt('mot-de-passe-distant'),
            'remote_directory' => 'in/factures',
        ], $o)
    );

    /** Substitue un disque local au disque distant que le service bâtirait. */
    $this->useFakeDisk = function (): FilesystemAdapter {
        $disk = Storage::fake('remote-double');

        Storage::shouldReceive('build')->andReturn($disk);

        return $disk;
    };
});

it('écrit le fichier dans le répertoire configuré', function (): void {
    $disk = ($this->useFakeDisk)();

    $this->transporter->send(($this->configuration)(), 'INV-2026-00042.xml', '<invoice/>', 'application/xml');

    $disk->assertExists('in/factures/INV-2026-00042.xml');

    expect($disk->get('in/factures/INV-2026-00042.xml'))->toBe('<invoice/>');
});

it('dépose à la racine quand aucun répertoire n’est configuré', function (): void {
    $disk = ($this->useFakeDisk)();

    $this->transporter->send(
        ($this->configuration)(['remote_directory' => null]),
        'facture.json',
        '{}',
        'application/json',
    );

    $disk->assertExists('facture.json');
});

/** §80 : un répertoire mal saisi n'écrit pas ailleurs sur le serveur du client. */
it('aplatit une remontée d’arborescence', function (): void {
    $disk = ($this->useFakeDisk)();

    $this->transporter->send(
        ($this->configuration)(['remote_directory' => '../../home/autre']),
        'facture.json',
        '{}',
        'application/json',
    );

    $disk->assertExists('home/autre/facture.json');
});

/** §125 : un serveur « du client » qui répond sur localhost est le nôtre. */
it('refuse un hôte interne avant toute connexion', function (): void {
    Storage::shouldReceive('build')->never();

    expect(fn () => $this->transporter->send(
        ($this->configuration)(['host' => '127.0.0.1']),
        'facture.json',
        '{}',
        'application/json',
    ))->toThrow(RuntimeException::class);
});

/**
 * §124 : le message du pilote peut contenir l'URL de connexion, donc le mot de
 * passe. Seule la classe de l'exception est reprise.
 */
it('ne laisse pas le mot de passe dans le message d’échec', function (): void {
    Storage::shouldReceive('build')->andThrow(
        new RuntimeException('Échec de connexion à sftp://tricolis:mot-de-passe-distant@sftp.client.example')
    );

    try {
        $this->transporter->send(($this->configuration)(), 'facture.json', '{}', 'application/json');

        $this->fail('Le transporteur aurait dû signaler l’échec.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())
            ->not->toContain('mot-de-passe-distant')
            ->and($exception->getMessage())->toContain('SFTP');
    }
});

it('signale un refus du serveur distant', function (): void {
    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('put')->andReturnFalse();

    Storage::shouldReceive('build')->andReturn($disk);

    expect(fn () => $this->transporter->send(($this->configuration)(), 'facture.json', '{}', 'application/json'))
        ->toThrow(RuntimeException::class, 'Le serveur distant a refusé le fichier.');
});
