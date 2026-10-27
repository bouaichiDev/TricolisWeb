<?php

/**
 * Origines CORS (Phase 8) : le portail de production se déclare par
 * `CORS_ALLOWED_ORIGINS`, et les origines `localhost` disparaissent en
 * production.
 */

/** Le fichier `config/cors.php` évalué avec ces variables d'environnement. */
function corsConfigWith(array $variables): array
{
    $previous = [];
    foreach ($variables as $name => $value) {
        $previous[$name] = [$_SERVER[$name] ?? null, $_ENV[$name] ?? null];
        $_SERVER[$name] = $_ENV[$name] = $value;
    }

    try {
        return require config_path('cors.php');
    } finally {
        foreach ($previous as $name => [$server, $env]) {
            if ($server === null) {
                unset($_SERVER[$name]);
            } else {
                $_SERVER[$name] = $server;
            }
            if ($env === null) {
                unset($_ENV[$name]);
            } else {
                $_ENV[$name] = $env;
            }
        }
    }
}

it('n’autorise en production que les origines déclarées', function (): void {
    $config = corsConfigWith([
        'APP_ENV' => 'production',
        'CORS_ALLOWED_ORIGINS' => 'https://tricolis.example.com, https://client.example.com',
    ]);

    expect($config['allowed_origins'])->toBe(['https://tricolis.example.com', 'https://client.example.com'])
        ->and($config['supports_credentials'])->toBeFalse();
});

it('garde le seul back-office historique quand rien n’est déclaré', function (): void {
    unset($_SERVER['CORS_ALLOWED_ORIGINS'], $_ENV['CORS_ALLOWED_ORIGINS']);

    expect(corsConfigWith(['APP_ENV' => 'production'])['allowed_origins'])->toBe(['https://tricolis.bouaichibadr.com']);
})->skip(getenv('CORS_ALLOWED_ORIGINS') !== false, 'CORS_ALLOWED_ORIGINS est défini dans l’environnement du processus.');

it('ajoute les origines localhost hors production', function (): void {
    $config = corsConfigWith(['APP_ENV' => 'testing', 'CORS_ALLOWED_ORIGINS' => 'https://tricolis.example.com']);

    expect($config['allowed_origins'])->toContain('https://tricolis.example.com')
        ->and($config['allowed_origins'])->toContain('http://localhost:5182');
});

it('répond au pré-vol du portail déclaré, pas à une autre origine', function (): void {
    config(['cors.allowed_origins' => ['https://client.example.com']]);

    $allowed = $this->withHeaders(['Origin' => 'https://client.example.com', 'Access-Control-Request-Method' => 'POST'])
        ->options('/api/v1/customer/auth/login');
    $other = $this->withHeaders(['Origin' => 'http://localhost:5180', 'Access-Control-Request-Method' => 'POST'])
        ->options('/api/v1/customer/auth/login');

    expect($allowed->headers->get('Access-Control-Allow-Origin'))->toBe('https://client.example.com')
        ->and($other->headers->get('Access-Control-Allow-Origin'))->not->toBe('http://localhost:5180');
});
