<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Organizations\Models\OrganizationUser;
use App\Shared\Dashboard\DashboardWidgetRegistry;

/**
 * Ce que voient les rôles d'un utilisateur, réunis.
 *
 * **Union, jamais intersection.** C'est déjà la règle des permissions —
 * `hasPermission()` s'arrête au premier rôle qui accorde — et celle du menu.
 * L'intersection aurait eu le défaut inverse, plus grave : **ajouter** un rôle
 * aurait retiré des widgets, ce que personne n'attend d'un ajout.
 *
 * Un widget que deux rôles montrent s'affiche une fois, au **plus petit rang
 * configuré**. Prendre le plus grand l'aurait relégué derrière des widgets
 * qu'un seul rôle réclame ; prendre celui du « rôle principal », comme le fait
 * le menu pour les noms, n'avait pas lieu d'être ici : un rang n'est pas un
 * nom, deux rangs se comparent sans qu'on ait à désigner un vainqueur.
 *
 * L'ordre final départage les rangs égaux par la clé. Sans ce second critère,
 * deux widgets de même rang se rendraient dans l'ordre où SQL a rendu les
 * rôles — c'est-à-dire dans un ordre différent d'un appel à l'autre.
 */
final readonly class UserDashboardWidgets
{
    /**
     * @param  array<string, int>  $positions  Clé du widget → plus petit rang.
     */
    private function __construct(private array $positions) {}

    public static function for(string $userId, string $organizationId): self
    {
        $membership = OrganizationUser::where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->with('roles:id')
            ->first();

        $roles = $membership?->roles->all() ?? [];

        // Aucun rôle, c'est « rien de configuré » — pas « rien à voir ». Le
        // propriétaire d'un organisme est dans ce cas tant qu'on ne lui en a
        // pas attribué : lui rendre un écran vide alors qu'il détient tous les
        // droits aurait ressemblé à une panne.
        if ($roles === []) {
            return self::fromCatalogueDefaults();
        }

        $positions = [];

        foreach ($roles as $role) {
            foreach (RoleDashboardWidgets::for($role->id)->all() as $key => $position) {
                $positions[$key] = isset($positions[$key])
                    ? min($positions[$key], $position)
                    : $position;
            }
        }

        return new self($positions);
    }

    /**
     * @return array<string, int>
     */
    private static function defaultPositions(): array
    {
        $positions = [];

        foreach (DashboardWidgetRegistry::defaults() as $widget) {
            $positions[$widget->key] = $widget->defaultPosition;
        }

        return $positions;
    }

    private static function fromCatalogueDefaults(): self
    {
        return new self(self::defaultPositions());
    }

    /**
     * Les clés retenues, triées comme elles s'afficheront.
     *
     * @return array<int, string>
     */
    public function orderedKeys(): array
    {
        $keys = array_keys($this->positions);

        usort($keys, fn (string $a, string $b): int => [$this->positions[$a], $a] <=> [$this->positions[$b], $b]);

        return $keys;
    }

    public function positionOf(string $key): int
    {
        return $this->positions[$key] ?? 0;
    }
}
