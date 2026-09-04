<?php

use App\Modules\Exports\Services\RemoteTargetGuard;

/**
 * Le contrôle des destinations configurées par un client.
 *
 * Une URL fournie par un tiers est une arme : notre serveur l'appellerait avec
 * ses propres droits réseau. Le §125 impose donc un refus avant tout appel, et
 * le §80 refuse qu'un répertoire distant remonte l'arborescence.
 */
beforeEach(function (): void {
    $this->guard = new RemoteTargetGuard;
});

describe('adresses HTTP', function (): void {
    it('assemble la base et le chemin', function (): void {
        expect($this->guard->httpUrl('https://client.example/', '/v1/invoices'))
            ->toBe('https://client.example/v1/invoices');
    });

    it('refuse un schéma qui n’est pas http', function (): void {
        expect(fn () => $this->guard->httpUrl('file:///etc/passwd', ''))
            ->toThrow(RuntimeException::class);

        expect(fn () => $this->guard->httpUrl('gopher://client.example', ''))
            ->toThrow(RuntimeException::class);
    });

    /** Le cas concret : le service de métadonnées de l'hébergeur. */
    it('refuse une adresse de métadonnées', function (): void {
        expect(fn () => $this->guard->httpUrl('http://169.254.169.254/latest/meta-data/', ''))
            ->toThrow(RuntimeException::class);
    });

    it('refuse la boucle locale et les plages privées', function (): void {
        foreach (['http://127.0.0.1:3306', 'http://10.0.0.5', 'http://192.168.1.20'] as $url) {
            expect(fn () => $this->guard->httpUrl($url, ''))->toThrow(RuntimeException::class);
        }
    });

    it('refuse une base sans hôte', function (): void {
        expect(fn () => $this->guard->httpUrl('', ''))->toThrow(RuntimeException::class);
    });
});

describe('hôtes de fichiers', function (): void {
    it('refuse un serveur qui est en réalité le nôtre', function (): void {
        expect(fn () => $this->guard->assertFileHost('127.0.0.1'))->toThrow(RuntimeException::class);
    });

    it('laisse passer un hôte externe', function (): void {
        expect(fn () => $this->guard->assertFileHost('sftp.client.example'))->not->toThrow(RuntimeException::class);
    });
});

describe('chemins distants', function (): void {
    /** §80 : le fichier reste dans le répertoire prévu. */
    it('retire les remontées d’arborescence', function (): void {
        expect($this->guard->safePath('../../etc/entrant'))->toBe('etc/entrant');
    });

    it('normalise les séparateurs et les vides', function (): void {
        expect($this->guard->safePath('\\in\\\\factures/./'))->toBe('in/factures');
    });

    it('accepte l’absence de répertoire', function (): void {
        expect($this->guard->safePath(''))->toBe('');
    });
});
