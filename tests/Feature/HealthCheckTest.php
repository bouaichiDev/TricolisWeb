<?php

use Illuminate\Support\Facades\DB;

/**
 * `/up` (Phase 8 portail, §33) : vert quand base, disque et cache répondent ;
 * en échec — sans détail exposé — quand l'un d'eux tombe.
 */
it('répond 200 quand base, disque privé et cache répondent', function (): void {
    $this->get('/up')->assertOk();
});

it('échoue quand la base ne répond plus, sans exposer sa configuration', function (): void {
    config(['app.debug' => false]);
    DB::shouldReceive('select')->andThrow(new RuntimeException('SQLSTATE[HY000] [2002] Connection refused to 10.0.0.5'));

    $response = $this->get('/up');

    expect($response->status())->toBe(500)
        ->and($response->getContent())->not->toContain('10.0.0.5')
        ->and($response->getContent())->not->toContain('SQLSTATE');
});
