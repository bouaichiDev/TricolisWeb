<?php

use App\Modules\Identity\Models\Permission;

/**
 * Cohérence entre le menu du frontend et le référentiel de permissions.
 *
 * Une entrée de menu qui exige une permission inexistante **disparaît en
 * silence** : `has()` renvoie `false`, l'entrée n'est jamais rendue, et aucune
 * erreur n'est levée. C'est arrivé — le menu exigeait `audit_logs.view` quand
 * le code réel est `audit.view`, et le journal d'audit était invisible pour
 * tout le monde, administrateurs compris.
 *
 * Le test lit le fichier de navigation plutôt qu'une liste recopiée : une liste
 * recopiée aurait le même défaut que ce qu'elle prétend vérifier.
 */
function navigationFile(): ?string
{
    $path = base_path('frontend/src/app/router/navigation.ts');

    return is_file($path) ? (string) file_get_contents($path) : null;
}

/**
 * @return array<int, string>
 */
function navigationPermissions(string $contents): array
{
    preg_match_all("/permission:\s*'([a-z_]+\.[a-z_]+)'/", $contents, $matches);

    return array_values(array_unique($matches[1]));
}

beforeEach(function (): void {
    $this->seed();
});

it('requires only permissions that exist in the reference', function (): void {
    $contents = navigationFile();

    if ($contents === null) {
        $this->markTestSkipped('Le frontend n’est pas présent dans cette copie de travail.');
    }

    $required = navigationPermissions($contents);
    $known = Permission::pluck('code')->all();

    expect($required)->not->toBeEmpty()
        ->and(array_diff($required, $known))->toBe([]);
});

/**
 * Les gardes de route déclarent les mêmes permissions que le menu ; une route
 * exigeant un code inexistant renvoie vers « Accès refusé » sans explication.
 */
it('guards routes with permissions that exist too', function (): void {
    $directory = base_path('frontend/src/app/router/routes');

    if (! is_dir($directory)) {
        $this->markTestSkipped('Le frontend n’est pas présent dans cette copie de travail.');
    }

    $required = [];

    foreach (glob($directory.'/*.tsx') ?: [] as $file) {
        preg_match_all("/guarded\(\s*'([a-z_]+\.[a-z_]+)'/", (string) file_get_contents($file), $matches);
        $required = array_merge($required, $matches[1]);
    }

    $known = Permission::pluck('code')->all();

    expect(array_unique($required))->not->toBeEmpty()
        ->and(array_diff(array_unique($required), $known))->toBe([]);
});
