<?php

use App\Modules\Exports\Services\RemoteTargetGuard;

/**
 * Garde SSRF commune aux pulls, pushes et transferts de fichiers (§44, §56).
 * La résolution DNS est simulée : aucun serveur réel n'est interrogé.
 */
beforeEach(function (): void {
    $this->guard = new RemoteTargetGuard(static fn (string $host): array => match ($host) {
        'internal.partner.example.com' => ['10.0.0.12'],
        'rebind.partner.example.com' => ['8.8.8.8', '127.0.0.1'],
        default => ['8.8.8.8'],
    });
});

it('refuse les destinations internes', function (string $url): void {
    expect(fn () => $this->guard->httpUrl($url, '/orders'))->toThrow(RuntimeException::class);
})->with([
    'localhost' => ['https://localhost'],
    'sous-domaine localhost' => ['https://api.localhost'],
    'loopback' => ['https://127.0.0.1'],
    'loopback IPv6' => ['https://[::1]'],
    'IPv4 encapsulée' => ['https://[::ffff:127.0.0.1]'],
    'métadonnées' => ['http://169.254.169.254'],
    'nom de métadonnées' => ['http://metadata.google.internal'],
    'réseau privé' => ['https://10.1.2.3'],
    'nom résolu en privé' => ['https://internal.partner.example.com'],
    'une des adresses est interne' => ['https://rebind.partner.example.com'],
    'identifiants dans l’URL' => ['https://user:secret@api.partner.example.com'],
    'schéma de fichier' => ['file:///etc/passwd'],
    'gopher' => ['gopher://api.partner.example.com'],
    'port hors plage' => ['https://api.partner.example.com:70000'],
]);

it('admet un hôte public et épingle l’adresse contrôlée', function (): void {
    $url = $this->guard->httpUrl('https://api.partner.example.com/', '/v1/orders');

    expect($url)->toBe('https://api.partner.example.com/v1/orders')
        ->and($this->guard->pinnedResolve($url))->toBe(['api.partner.example.com:443:8.8.8.8']);
});

it('neutralise la remontée d’arborescence dans un chemin distant', function (): void {
    expect($this->guard->safePath('../../etc/passwd'))->toBe('etc/passwd')
        ->and($this->guard->safePath('outbox\\..\\..\\root'))->toBe('outbox/root')
        ->and($this->guard->httpUrl('https://api.partner.example.com', '../../admin'))->toBe('https://api.partner.example.com/admin');
});

it('refuse un serveur de fichiers interne', function (): void {
    expect(fn () => $this->guard->assertFileHost('127.0.0.1', 22))->toThrow(RuntimeException::class)
        ->and(fn () => $this->guard->assertFileHost('sftp.partner.example.com', 0))->toThrow(RuntimeException::class);
});
