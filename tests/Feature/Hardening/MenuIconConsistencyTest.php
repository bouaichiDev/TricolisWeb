<?php

use App\Shared\Enums\RoleScope;
use App\Shared\Menu\MenuCatalogue;
use App\Shared\Menu\MenuEntry;
use App\Shared\Menu\MenuIcons;

/**
 * Cohérence entre les icônes que le backend accepte et celles que le frontend
 * sait rendre.
 *
 * Une icône est un composant React : la base n'en stocke que le **nom**, et
 * `menuIcons.ts` le résout. Un nom accepté par la validation mais absent de
 * cette table **échoue en silence** — l'entrée retombe sur l'icône neutre, et
 * l'administrateur qui vient de la choisir croit avoir réussi. C'est la même
 * classe de défaut que la permission inexistante du menu : rien ne casse, tout
 * est faux.
 *
 * Le test lit le fichier frontend plutôt qu'une liste recopiée : une liste
 * recopiée aurait le défaut qu'elle prétend vérifier.
 */
function menuIconsFile(): ?string
{
    $path = base_path('frontend/src/modules/menu/components/menuIcons.ts');

    return is_file($path) ? (string) file_get_contents($path) : null;
}

/**
 * Noms de la table `ICONS`, alias d'import compris — `Map: MapIcon` déclare
 * bien l'icône « Map ».
 *
 * La fin de ligne est regardée en avant plutôt que consommée : la consommer
 * ferait manquer une ligne sur deux, `preg_match_all` ne revenant pas en
 * arrière. Le `\r` est toléré — un poste Windows peut enregistrer le fichier en
 * CRLF, et le test passerait alors sur une liste vide, ce qu'il refuse.
 *
 * @return array<int, string>
 */
function frontendIconNames(string $contents): array
{
    $table = mb_strstr($contents, 'const ICONS');
    $table = $table === false ? '' : (string) mb_strstr($table, '}', true);

    preg_match_all('/\n {2}([A-Za-z0-9]+)(?:: *[A-Za-z0-9]+)?,(?=\r?\n)/', $table, $matches);

    return array_values(array_unique($matches[1]));
}

it('offers exactly the icons the frontend can render', function (): void {
    $contents = menuIconsFile();

    if ($contents === null) {
        $this->markTestSkipped('Frontend absent de cette installation.');
    }

    $frontend = frontendIconNames($contents);

    expect($frontend)->not->toBeEmpty()
        ->and(array_values(array_diff($frontend, MenuIcons::NAMES)))->toBe([])
        ->and(array_values(array_diff(MenuIcons::NAMES, $frontend)))->toBe([]);
});

/**
 * Une entrée du catalogue qui citerait une icône hors table s'afficherait déjà
 * avec l'icône neutre, avant même qu'une organisation touche à quoi que ce soit.
 */
it('only names icons the frontend can render in the catalogue', function (): void {
    $used = array_map(
        static fn (MenuEntry $entry): string => $entry->icon,
        [...MenuCatalogue::forScope(RoleScope::ORGANIZATION), ...MenuCatalogue::forScope(RoleScope::PLATFORM)],
    );

    expect(array_values(array_unique(array_diff($used, MenuIcons::NAMES))))->toBe([]);
});
