<?php

/**
 * La documentation OpenAPI du périmètre externe dit ce que l'API accepte
 * vraiment (Phase 8, §35) : pas de `customerId` présenté comme un filtre, pas
 * de filtre ignoré sur l'API par clé, l'en-tête `X-Api-Key` documenté.
 */
beforeEach(function (): void {
    $path = tempnam(sys_get_temp_dir(), 'openapi-').'.json';
    $this->artisan('scramble:export', ['--path' => $path])->assertSuccessful();
    $this->paths = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR)['paths'];
    @unlink($path);

    $this->parameters = fn (string $path, string $method = 'get'): array => array_column($this->paths[$path][$method]['parameters'] ?? [], 'name');
});

it('ne présente aucun champ de client ou d’organisation comme paramètre externe', function (): void {
    $leaks = [];
    foreach ($this->paths as $path => $operations) {
        if (! preg_match('#^/v1/(customer|client|public)/#', $path)) {
            continue;
        }
        foreach ($operations as $method => $operation) {
            $names = array_column(is_array($operation) ? ($operation['parameters'] ?? []) : [], 'name');
            if (array_intersect($names, ['customerId', 'organizationId', 'customer_id', 'organization_id']) !== []) {
                $leaks[] = strtoupper($method).' '.$path;
            }
        }
    }

    expect($leaks)->toBe([]);
});

it('documente les seuls filtres lus par l’API par clé, et sa clé', function (): void {
    expect(($this->parameters)('/v1/client/orders'))->toEqualCanonicalizing(['search', 'status', 'perPage', 'page', 'X-Api-Key'])
        ->and(($this->parameters)('/v1/client/me'))->toContain('X-Api-Key')
        ->and(array_keys($this->paths['/v1/client/orders']['get']['responses']))->toContain(401, 429); // clés numériques : entiers une fois décodées
});

it('documente toutes les routes du catalogue du portail', function (): void {
    expect($this->paths)->toHaveKeys([
        '/v1/customer/catalog/catalogs',
        '/v1/customer/catalog/items',
        '/v1/customer/catalog/items/{item}',
    ])->and($this->paths['/v1/customer/catalog/items'])->toHaveKeys(['get', 'post']);
});
