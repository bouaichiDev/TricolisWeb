<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\RoleMenuItem;
use App\Shared\Menu\MenuCatalogue;
use App\Shared\Menu\MenuEntry;
use App\Shared\Menu\MenuIcons;
use Illuminate\Support\Collection;

/**
 * Ce qu'un rôle a choisi, posé par-dessus le catalogue.
 *
 * L'absence de ligne vaut « valeurs par défaut ». C'est ce qui permet à une
 * entrée ajoutée au catalogue d'apparaître partout sans migration de données,
 * et au résolveur de rester correct pour un rôle qu'on n'a jamais réglé.
 *
 * Une question par chose réglable, plutôt qu'un tableau brut à interroger de
 * cinq façons chez l'appelant : chacune porte sa règle de repli, et c'est là
 * qu'elle se relit.
 */
final readonly class RoleMenuOverrides
{
    /**
     * @param  Collection<string, RoleMenuItem>  $settings
     * @param  array<int, string>  $customGroupCodes  Groupes que le rôle s'est créés.
     */
    private function __construct(private Collection $settings, private array $customGroupCodes) {}

    /**
     * @param  array<int, string>  $customGroupCodes
     */
    public static function for(?string $roleId, array $customGroupCodes = []): self
    {
        if ($roleId === null) {
            return new self(collect(), []);
        }

        return new self(
            RoleMenuItem::where('role_id', $roleId)->get()->keyBy('code'),
            $customGroupCodes,
        );
    }

    /**
     * L'absence de ligne vaut « visible » : un rôle ne perd pas les entrées
     * ajoutées au catalogue après lui.
     *
     * Une entrée `alwaysVisible` l'emporte sur le choix. Elles ne sont plus
     * qu'une — « Mon organisation » — et elle garde à tout rôle un pied dans
     * l'administration.
     */
    public function isEnabled(MenuEntry $entry): bool
    {
        if ($entry->alwaysVisible) {
            return true;
        }

        return $this->settings->get($entry->code)?->is_visible ?? true;
    }

    public function positionOf(MenuEntry $entry): int
    {
        return $this->settings->get($entry->code)?->position ?? $entry->position;
    }

    /**
     * Libellé choisi, ou null pour suivre la clé i18n.
     *
     * Une chaîne vide vaut null : l'écran de réglage vide le champ pour revenir
     * au défaut, et enregistrer « » afficherait une entrée sans nom.
     */
    public function labelOf(MenuEntry $entry): ?string
    {
        $label = $this->settings->get($entry->code)?->label;

        return $label === null || $label === '' ? null : $label;
    }

    /**
     * Le rôle a-t-il choisi lui-même le rattachement de l'entrée ?
     *
     * La question se pose à part parce que la réponse « au premier niveau » est
     * un `null` : sans ce drapeau, elle serait indistinguable de « je n'ai rien
     * choisi », et l'entrée retournerait dans son groupe au rechargement.
     *
     * Un rattachement devenu impossible — le groupe cible a quitté le catalogue,
     * ou le rôle a supprimé le groupe qu'il avait créé — est traité comme une
     * absence de choix, et l'entrée revient à son groupe d'origine plutôt que
     * de se retrouver à la racine sans qu'on l'ait demandé.
     */
    public function reparents(MenuEntry $entry): bool
    {
        $setting = $this->settings->get($entry->code);

        if ($setting === null || ! $setting->parent_overridden) {
            return false;
        }

        return MenuCatalogue::canReparent($entry, $setting->parent_code, $this->customGroupCodes);
    }

    public function parentOf(MenuEntry $entry): ?string
    {
        return $this->settings->get($entry->code)?->parent_code;
    }

    /**
     * Icône choisie, si le frontend sait encore la rendre.
     *
     * Le nom est reconfronté au catalogue d'icônes **à la lecture**, pas
     * seulement à l'écriture : une icône retirée de `menuIcons.ts` laisserait
     * sinon des lignes pointant vers un composant absent, et l'entrée tomberait
     * sur l'icône neutre au lieu de retrouver la sienne.
     */
    public function iconOf(MenuEntry $entry): ?string
    {
        $icon = $this->settings->get($entry->code)?->icon;

        return $icon !== null && MenuIcons::knows($icon) ? $icon : null;
    }
}
