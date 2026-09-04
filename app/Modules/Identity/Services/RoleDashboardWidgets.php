<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\RoleDashboardConfiguration;
use App\Shared\Dashboard\DashboardWidget;
use App\Shared\Dashboard\DashboardWidgetRegistry;

/**
 * Ce qu'un rôle a choisi de montrer, confronté au catalogue.
 *
 * Toute la subtilité tient en deux lignes :
 *
 * ```
 * aucune configuration → les widgets defaultEnabled du catalogue
 * widgets: []          → aucun widget, parce qu'on l'a demandé
 * ```
 *
 * Les confondre reviendrait à rendre le second inatteignable : un rôle qui vide
 * son tableau de bord le retrouverait rempli au rechargement suivant, sans rien
 * pour dire pourquoi.
 *
 * Une clé inconnue du catalogue est **ignorée à la lecture**, pas seulement
 * refusée à l'écriture. La validation empêche d'en écrire une ; elle ne peut
 * rien contre un widget retiré du code après coup, dont la clé resterait en
 * base. L'ignorer ici la fait disparaître d'elle-même, sans migration.
 */
final readonly class RoleDashboardWidgets
{
    /**
     * @param  array<string, int>  $positions  Clé du widget → rang choisi.
     */
    private function __construct(private array $positions) {}

    public static function for(string $roleId): self
    {
        $configuration = RoleDashboardConfiguration::where('role_id', $roleId)->first();

        if ($configuration === null) {
            return self::fromCatalogueDefaults();
        }

        return new self(self::readSelection($configuration->widgets ?? []));
    }

    private static function fromCatalogueDefaults(): self
    {
        $positions = [];

        foreach (DashboardWidgetRegistry::defaults() as $widget) {
            $positions[$widget->key] = $widget->defaultPosition;
        }

        return new self($positions);
    }

    /**
     * Relit la colonne JSON sans lui faire confiance.
     *
     * Elle a été validée à l'écriture, mais elle survit aux versions : une clé
     * qui n'existe plus, un rang devenu texte, une entrée qui n'est même pas un
     * tableau. Chacun de ces cas est écarté ligne par ligne plutôt que de faire
     * échouer la lecture entière — un tableau de bord qui refuse de s'afficher
     * à cause d'une clé périmée serait une panne pour un détail.
     *
     * @param  array<int|string, mixed>  $raw
     * @return array<string, int>
     */
    private static function readSelection(array $raw): array
    {
        $positions = [];

        foreach ($raw as $entry) {
            if (! is_array($entry) || ! isset($entry['key']) || ! is_string($entry['key'])) {
                continue;
            }

            $widget = DashboardWidgetRegistry::find($entry['key']);

            if ($widget === null) {
                continue;
            }

            $positions[$widget->key] = is_numeric($entry['position'] ?? null)
                ? (int) $entry['position']
                : $widget->defaultPosition;
        }

        return $positions;
    }

    public function isEnabled(DashboardWidget $widget): bool
    {
        return array_key_exists($widget->key, $this->positions);
    }

    /**
     * Rang choisi, ou celui du catalogue pour un widget que ce rôle ne montre
     * pas.
     *
     * L'écran de réglage affiche les widgets décochés parmi les autres : sans
     * rang, ils se rassembleraient tous en tête de liste, dans un ordre que
     * rien ne justifie.
     */
    public function positionOf(DashboardWidget $widget): int
    {
        return $this->positions[$widget->key] ?? $widget->defaultPosition;
    }

    /**
     * @return array<string, int>
     */
    public function all(): array
    {
        return $this->positions;
    }
}
