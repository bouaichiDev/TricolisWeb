<?php

use App\Modules\Integrations\Services\Validation\ImportValidationRules;

/**
 * `validationRules` (§7) : des règles choisies dans une liste fermée, jamais
 * une règle, une expression ou une classe arbitraire fournie par le JSON.
 */
it('accepte les règles contrôlées et les options connues', function (): void {
    expect(ImportValidationRules::problems([
        'externalReference' => ['required', 'string', 'max:64'],
        'lines.*.quantity' => 'numeric|min:1',
        'orderType' => 'in:delivery,pickup',
        '@onDuplicate' => 'update',
        '@requireReference' => true,
    ]))->toBe([]);
});

it('refuse ce qui ferait exécuter, lire une table ou bloquer le serveur', function (array $rules): void {
    expect(ImportValidationRules::problems($rules))->not->toBe([]);
})->with([
    'regex' => [['externalReference' => 'regex:/(a+)+$/']],
    'exists' => [['externalReference' => 'exists:users,email']],
    'classe' => [['externalReference' => ['App\\Rules\\Evil']]],
    'closure texte' => [['externalReference' => 'function () {}']],
    'paramètre non numérique' => [['externalReference' => 'max:abc']],
    'chemin invalide' => [['../etc' => 'required']],
    'option inconnue' => [['@eval' => 'x']],
    'stratégie inconnue' => [['@onDuplicate' => 'merge']],
]);

it('ne choisit jamais UPDATE sans configuration : le défaut est SKIP', function (): void {
    expect(ImportValidationRules::onDuplicate(null))->toBe('skip')
        ->and(ImportValidationRules::onDuplicate(['@onDuplicate' => 'nimportequoi']))->toBe('skip')
        ->and(ImportValidationRules::onDuplicate(['@onDuplicate' => 'update']))->toBe('update');
});
