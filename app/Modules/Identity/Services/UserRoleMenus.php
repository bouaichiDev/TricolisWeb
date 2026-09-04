<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RoleMenuItem;
use App\Modules\Organizations\Models\OrganizationUser;

/**
 * Les rôles d'un utilisateur, du point de vue du menu.
 *
 * Un utilisateur peut en cumuler plusieurs, et chacun porte désormais un menu
 * entier. Deux réponses en découlent, et elles ne suivent pas la même règle :
 *
 * - **la présentation** — ordre, noms, icônes, groupes — ne peut pas se fondre.
 *   Deux rôles qui nomment la même entrée autrement obligent à choisir : c'est
 *   le **rôle principal** qui l'emporte, celui dont le code vient en premier.
 *   Le tri par code rend ce choix stable et lisible, et l'administrateur peut
 *   en changer en renommant ;
 * - **la visibilité** se combine, elle, par **union** : une entrée s'affiche
 *   dès qu'un seul rôle la montre.
 *
 * Ce départage est le prix du réglage par rôle. L'ancien modèle l'évitait en
 * refusant de régler la présentation sur un rôle ; il ne se paie que sur les
 * comptes multi-rôles.
 */
final readonly class UserRoleMenus
{
    /**
     * @param  array<int, Role>  $roles  Triés par code.
     */
    private function __construct(public array $roles) {}

    public static function for(string $userId, ?string $organizationId): self
    {
        if ($organizationId === null) {
            return new self([]);
        }

        $membership = OrganizationUser::where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->with('roles')
            ->first();

        return new self($membership?->roles->sortBy('code')->values()->all() ?? []);
    }

    /** Celui qui gouverne la présentation, ou null si l'utilisateur n'a aucun rôle. */
    public function primary(): ?Role
    {
        return $this->roles[0] ?? null;
    }

    public function isEmpty(): bool
    {
        return $this->roles === [];
    }

    /**
     * Codes qu'**aucun** rôle ne montre.
     *
     * La question se pose à l'envers, et c'est ce qui la rend juste : on cherche
     * ce que *tous* masquent. Un rôle sans ligne pour un code le montre par
     * défaut, et il suffit d'un tel rôle pour que l'entrée reste — ce qu'une
     * liste des codes montrés, bâtie sur les lignes existantes, manquerait
     * exactement.
     *
     * @return array<int, string>
     */
    public function hiddenByEveryRole(): array
    {
        $hidden = null;

        foreach ($this->roles as $role) {
            $own = RoleMenuItem::where('role_id', $role->id)
                ->where('is_visible', false)
                ->pluck('code')
                ->all();

            $hidden = $hidden === null ? $own : array_intersect($hidden, $own);

            if ($hidden === []) {
                break;
            }
        }

        return array_values($hidden ?? []);
    }
}
