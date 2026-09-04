<?php

use App\Modules\Exports\Models\CustomerExportConfiguration;
use App\Modules\Exports\Services\Transports\RestApiExportTransporter;
use App\Shared\Support\Secret;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * La porte par laquelle l'API du client se laisse appeler.
 *
 * Aucun appel ne sort : le client HTTP est doublé, et ce qu'on inspecte est la
 * requête telle qu'elle serait partie — l'en-tête posé, sa valeur, et ce qui
 * n'y figure pas.
 *
 * Le secret vient toujours du champ chiffré. Ces cas le vérifient autant que le
 * mode : un jeton qui se saisirait dans `settings` serait lisible en base et
 * rendu à l'écran, ce que le §72 interdit.
 */
uses(TestCase::class);

beforeEach(function (): void {
    $this->transporter = app(RestApiExportTransporter::class);

    $this->configuration = fn (array $settings = [], array $o = []): CustomerExportConfiguration => new CustomerExportConfiguration(
        array_merge([
            'id' => '01JBADR000000000000000TEST',
            'transport' => 'rest_api',
            'host' => 'https://api.client.example/factures',
            'username' => 'tricolis',
            'encrypted_password' => Secret::encrypt('le-secret'),
            'settings' => $settings,
        ], $o)
    );

    $this->send = function (array $settings = [], array $o = []): Request {
        Http::fake(['*' => Http::response('', 200)]);

        $this->transporter->send(
            ($this->configuration)($settings, $o),
            'INV-2026-00042.json',
            '{}',
            'application/json',
        );

        return Http::recorded()->last()[0];
    };
});

it('pose un jeton porteur quand aucun mode n’est déclaré', function (): void {
    // Retro-compatibilite : les destinations posees avant les modes
    // continuent d'envoyer leur secret en Bearer.
    expect(($this->send)()->header('Authorization'))->toBe(['Bearer le-secret']);
});

it('n’envoie rien quand le mode est « aucune »', function (): void {
    expect(($this->send)(['authType' => 'none'])->header('Authorization'))->toBe([]);
});

it('n’envoie rien quand aucun secret n’est enregistré', function (): void {
    $request = ($this->send)([], ['encrypted_password' => null]);

    expect($request->header('Authorization'))->toBe([]);
});

it('compose un en-tête Basic depuis l’identifiant et le secret', function (): void {
    $request = ($this->send)(['authType' => 'basic']);

    expect($request->header('Authorization'))->toBe(['Basic '.base64_encode('tricolis:le-secret')]);
});

it('place la clé dans l’en-tête nommé par le client', function (): void {
    $request = ($this->send)(['authType' => 'api_key', 'apiKeyHeader' => 'X-Client-Key']);

    expect($request->header('X-Client-Key'))->toBe(['le-secret'])
        ->and($request->header('Authorization'))->toBe([]);
});

it('retombe sur X-Api-Key sans nom d’en-tête', function (): void {
    expect(($this->send)(['authType' => 'api_key'])->header('X-Api-Key'))->toBe(['le-secret']);
});

/**
 * §72 : accepter « Authorization » ici laisserait contourner les autres modes
 * par un simple réglage lisible en base.
 */
it('refuse de détourner l’en-tête Authorization', function (): void {
    $request = ($this->send)(['authType' => 'api_key', 'apiKeyHeader' => 'authorization']);

    expect($request->header('Authorization'))->toBe([])
        ->and($request->header('X-Api-Key'))->toBe(['le-secret']);
});

describe('OAuth2', function (): void {
    beforeEach(function (): void {
        Cache::flush();

        $this->oauth = [
            'authType' => 'oauth2',
            'tokenUrl' => 'https://auth.client.example/oauth/token',
            'clientId' => 'tricolis-app',
            'scope' => 'invoices.write',
        ];
    });

    it('échange les identifiants contre un jeton, puis l’envoie', function (): void {
        Http::fake([
            'auth.client.example/*' => Http::response(['access_token' => 'jeton-court'], 200),
            '*' => Http::response('', 200),
        ]);

        $this->transporter->send(
            ($this->configuration)($this->oauth),
            'INV.json',
            '{}',
            'application/json',
        );

        $calls = Http::recorded();

        expect($calls[0][0]->data())->toMatchArray([
            'grant_type' => 'client_credentials',
            'client_id' => 'tricolis-app',
            'client_secret' => 'le-secret',
            'scope' => 'invoices.write',
        ])
            ->and($calls[1][0]->header('Authorization'))->toBe(['Bearer jeton-court']);
    });

    /** Redemander un jeton par facture ferait plafonner le client sur ses quotas. */
    it('garde le jeton en cache entre deux envois', function (): void {
        Http::fake([
            'auth.client.example/*' => Http::response(['access_token' => 'jeton-court'], 200),
            '*' => Http::response('', 200),
        ]);

        foreach (['A.json', 'B.json'] as $file) {
            $this->transporter->send(($this->configuration)($this->oauth), $file, '{}', 'application/json');
        }

        $tokenCalls = Http::recorded()->filter(
            static fn (array $call): bool => str_contains($call[0]->url(), 'auth.client.example'),
        );

        expect($tokenCalls)->toHaveCount(1);
    });

    it('refuse un mode OAuth2 sans URL de jeton', function (): void {
        Http::fake();

        expect(fn () => $this->transporter->send(
            ($this->configuration)(['authType' => 'oauth2']),
            'INV.json',
            '{}',
            'application/json',
        ))->toThrow(RuntimeException::class, 'URL de jeton');
    });

    /** §125 : le serveur de jetons est une destination distante comme une autre. */
    it('refuse un serveur de jetons interne', function (): void {
        Http::fake();

        expect(fn () => $this->transporter->send(
            ($this->configuration)(['authType' => 'oauth2', 'tokenUrl' => 'http://169.254.169.254/token']),
            'INV.json',
            '{}',
            'application/json',
        ))->toThrow(RuntimeException::class);
    });

    /** Le corps d'une erreur OAuth2 reprend volontiers les identifiants reçus. */
    it('ne rapporte que le statut quand le serveur de jetons refuse', function (): void {
        Http::fake([
            'auth.client.example/*' => Http::response(['error' => 'invalid_client le-secret'], 401),
        ]);

        expect(fn () => $this->transporter->send(
            ($this->configuration)($this->oauth),
            'INV.json',
            '{}',
            'application/json',
        ))->toThrow(RuntimeException::class, 'a répondu 401');
    });
});
