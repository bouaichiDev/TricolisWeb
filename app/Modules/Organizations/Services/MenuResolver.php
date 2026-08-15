<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\PlatformAccess;
use App\Modules\Organizations\Models\OrganizationMenuItem;
use App\Policies\BaseOrganizationPolicy;
use App\Shared\Enums\RoleScope;
use App\Shared\Menu\MenuCatalogue;
use App\Shared\Menu\MenuEntry;
use Illuminate\Support\Collection;

/**
 * Compose le menu effectif d'un utilisateur dans son organisation.
 *
 * Trois filtres, dans cet ordre :
 *
 * 1. **la portée** — un compte plateforme reçoit le menu plateforme, pas le
 *    menu d'organisme expurgé : les clients et les agences appartiennent aux
 *    organismes ;
 * 2. **les réglages de l'organisation** — ce qu'elle a choisi de masquer. Un
 *    transporteur qui n'utilise pas une fonction n'a pas à en voir l'entrée ;
 * 3. **les permissions de l'utilisateur** — une entrée dont il n'a pas le droit
 *    ne lui est pas proposée.
 *
 * L'ordre compte : masquer une entrée au niveau de l'organisation la retire
 * pour tout le monde, y compris le propriétaire, alors qu'une permission
 * manquante ne concerne qu'un utilisateur.
 */
class MenuResolver extends BaseOrganizationPolicy
{
    public function __construct(private readonly PlatformAccess $platform) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function resolve(User $user, ?string $organizationId): array
    {
        $scope = $this->platform->isPlatformAdmin($user) ? RoleScope::PLATFORM : RoleScope::ORGANIZATION;
        $settings = $this->settingsFor($organizationId);

        $visible = [];

        foreach (MenuCatalogue::forScope($scope) as $entry) {
            if (! $this->isEnabled($entry, $settings)) {
                continue;
            }

            if (! $this->isPermitted($user, $entry, $organizationId, $scope)) {
                continue;
            }

            $visible[$entry->code] = $entry->toArray(true, $this->positionOf($entry, $settings));
        }

        // Un groupe dont plus aucun enfant ne subsiste n'a rien à ouvrir : il
        // afficherait un titre vide, ce que le §10 interdit.
        foreach ($visible as $code => $item) {
            if ($item['route'] === null && ! $this->hasVisibleChild($code, $visible)) {
                unset($visible[$code]);
            }
        }

        $items = array_values($visible);
        usort($items, static fn (array $a, array $b): int => $a['position'] <=> $b['position']);

        return $items;
    }

    /**
     * Catalogue complet destiné à l'écran de configuration, avec l'état choisi.
     *
     * Non filtré par les permissions : configurer le menu de l'organisation
     * n'est pas la même chose que le parcourir, et masquer les entrées qu'on ne
     * peut pas ouvrir soi-même empêcherait de les régler pour les autres.
     *
     * @return array<int, array<string, mixed>>
     */
    public function catalogue(?string $organizationId): array
    {
        $settings = $this->settingsFor($organizationId);

        $items = array_map(
            fn (MenuEntry $entry): array => $entry->toArray(
                $this->isEnabled($entry, $settings),
                $this->positionOf($entry, $settings),
            ),
            MenuCatalogue::forScope(RoleScope::ORGANIZATION),
        );

        usort($items, static fn (array $a, array $b): int => $a['position'] <=> $b['position']);

        return $items;
    }

    /**
     * @return Collection<string, OrganizationMenuItem>
     */
    private function settingsFor(?string $organizationId): Collection
    {
        if ($organizationId === null) {
            return collect();
        }

        return OrganizationMenuItem::where('organization_id', $organizationId)->get()->keyBy('code');
    }

    /**
     * @param  Collection<string, OrganizationMenuItem>  $settings
     */
    private function isEnabled(MenuEntry $entry, Collection $settings): bool
    {
        if ($entry->alwaysVisible) {
            return true;
        }

        return $settings->get($entry->code)?->is_visible ?? true;
    }

    /**
     * @param  Collection<string, OrganizationMenuItem>  $settings
     */
    private function positionOf(MenuEntry $entry, Collection $settings): int
    {
        return $settings->get($entry->code)?->position ?? $entry->position;
    }

    /**
     * Un groupe n'a pas de permission propre : il s'ouvre si un enfant s'ouvre.
     */
    private function isPermitted(User $user, MenuEntry $entry, ?string $organizationId, RoleScope $scope): bool
    {
        if ($entry->permission === null) {
            return true;
        }

        if ($scope === RoleScope::PLATFORM) {
            return true;
        }

        return $this->hasPermission($user, $organizationId, $entry->permission);
    }

    /**
     * @param  array<string, array<string, mixed>>  $visible
     */
    private function hasVisibleChild(string $code, array $visible): bool
    {
        foreach ($visible as $item) {
            if ($item['parent'] === $code) {
                return true;
            }
        }

        return false;
    }
}
