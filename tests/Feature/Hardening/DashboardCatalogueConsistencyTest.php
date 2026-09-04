<?php

use App\Modules\Dashboard\Services\DashboardContext;
use App\Modules\Dashboard\Services\DashboardDataSources;
use App\Modules\Identity\Models\Permission;
use App\Shared\Dashboard\DashboardWidget;
use App\Shared\Dashboard\DashboardWidgetRegistry;
use App\Shared\Dashboard\DashboardWidgetType;

/**
 * Ce qui empêche le catalogue des widgets de dériver.
 *
 * Un widget se déclare à trois endroits : sa définition, son calcul, ses deux
 * clés i18n. Manquer l'un des trois ne casse **rien** — et c'est bien le
 * problème :
 *
 * - une permission qui n'existe pas rend le widget invisible pour tout le
 *   monde, sans erreur. C'est le bug `audit_logs.view` du menu, transposé ;
 * - une route absente du routeur React mène à « Page introuvable », depuis une
 *   carte qui avait l'air normale ;
 * - une clé i18n manquante affiche la clé brute — `dashboardWidgets.x.label` —
 *   au milieu d'un tableau de bord ;
 * - un calcul oublié rend une carte vide, indistinguable d'une carte à zéro.
 *
 * Les fichiers du frontend sont **lus**, jamais recopiés ici : une liste
 * recopiée aurait exactement le défaut qu'elle prétend vérifier.
 */
function reactRouterPaths(): ?array
{
    $directory = base_path('frontend/src/app/router/routes');

    if (! is_dir($directory)) {
        return null;
    }

    $paths = [];

    foreach (glob($directory.'/*.tsx') ?: [] as $file) {
        preg_match_all('/path="([^"]+)"/', (string) file_get_contents($file), $matches);
        $paths = [...$paths, ...$matches[1]];
    }

    return array_values(array_unique($paths));
}

function frontendTranslations(): ?array
{
    $path = base_path('frontend/src/app/i18n/locales/fr.json');

    if (! is_file($path)) {
        return null;
    }

    return json_decode((string) file_get_contents($path), true);
}

it('names only permissions that exist in the reference', function (): void {
    $this->seed();

    $required = array_values(array_unique(array_map(
        static fn (DashboardWidget $widget): string => $widget->requiredPermission,
        DashboardWidgetRegistry::all(),
    )));

    $known = Permission::pluck('code')->all();

    expect($required)->not->toBeEmpty()
        ->and(array_diff($required, $known))->toBe([]);
});

it('names only routes the React router declares', function (): void {
    $declared = reactRouterPaths();

    if ($declared === null) {
        $this->markTestSkipped('Le frontend n’est pas présent dans cette copie de travail.');
    }

    $targets = array_values(array_filter(array_map(
        static fn (DashboardWidget $widget): ?string => $widget->route,
        DashboardWidgetRegistry::all(),
    )));

    expect($targets)->not->toBeEmpty()
        ->and(array_diff($targets, $declared))->toBe([]);
});

/**
 * Deux clés par widget, et les deux sont utilisées : le libellé sur la carte,
 * la description dans l'écran de réglage. Une description absente y laisserait
 * une ligne d'apparence technique sous un nom parfaitement lisible.
 */
it('gives every widget both of its translation keys', function (): void {
    $translations = frontendTranslations();

    if ($translations === null) {
        $this->markTestSkipped('Le frontend n’est pas présent dans cette copie de travail.');
    }

    $missing = [];

    foreach (DashboardWidgetRegistry::all() as $widget) {
        $entry = $translations['dashboardWidgets'][$widget->key] ?? null;

        if (! is_array($entry) || ! isset($entry['label'], $entry['description'])) {
            $missing[] = $widget->key;
        }
    }

    expect($missing)->toBe([]);
});

it('translates every widget type and category', function (): void {
    $translations = frontendTranslations();

    if ($translations === null) {
        $this->markTestSkipped('Le frontend n’est pas présent dans cette copie de travail.');
    }

    foreach (DashboardWidgetRegistry::all() as $widget) {
        expect($translations['dashboardWidgetTypes'][$widget->type->value] ?? null)->not->toBeNull()
            ->and($translations['dashboardCategories'][$widget->category->value] ?? null)->not->toBeNull();
    }
});

it('declares each key once', function (): void {
    $keys = array_map(
        static fn (DashboardWidget $widget): string => $widget->key,
        DashboardWidgetRegistry::all(),
    );

    expect(array_keys(array_filter(array_count_values($keys), static fn (int $count): bool => $count > 1)))->toBe([]);
});

/**
 * Le test qui coûte le plus, et qui rapporte le plus : **chaque widget est
 * réellement calculé**.
 *
 * Une clé déclarée sans branche dans sa source de données traverse tout le
 * système sans rien signaler — configuration acceptée, widget servi, `data` à
 * `null` — et rend une carte vide qu'on prend pour une carte à zéro. Ce test
 * les joue tous, sur une base vide : les chiffres sont donc nuls, mais la
 * réponse doit exister.
 *
 * Les actions rapides sont exclues, et c'est le seul type sans donnée : elles
 * n'affichent qu'un libellé et une destination.
 */
it('computes a value for every widget that carries one', function (): void {
    $this->seed();

    $organization = authOrganization();
    $widgets = array_values(array_filter(
        DashboardWidgetRegistry::all(),
        static fn (DashboardWidget $widget): bool => $widget->type !== DashboardWidgetType::QUICK_ACTION,
    ));

    $data = app(DashboardDataSources::class)->resolve(
        $widgets,
        DashboardContext::forOrganization($organization->id),
    );

    $unresolved = array_values(array_filter(
        array_map(static fn (DashboardWidget $widget): string => $widget->key, $widgets),
        static fn (string $key): bool => ($data[$key] ?? null) === null,
    ));

    expect($unresolved)->toBe([]);
});
