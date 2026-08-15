<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Organizations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Organizations\UpdateMenuRequest;
use App\Modules\Identity\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationMenuItem;
use App\Modules\Organizations\Services\MenuResolver;
use App\Shared\Http\Responses\ApiResponse;
use App\Shared\Menu\MenuCatalogue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Menu de navigation de l'organisation active.
 *
 * Le catalogue des entrées vit dans le code : route, icône et clé i18n y sont
 * couplées au frontend. Ce que l'organisation choisit — quelles entrées elle
 * voit, dans quel ordre — vit en base.
 */
class MenuController extends Controller
{
    public function __construct(private readonly MenuResolver $menu) {}

    /**
     * Menu effectif de l'utilisateur connecté.
     *
     * Aucune permission métier : le menu **est** la projection des permissions
     * de l'appelant. Une entrée qu'il n'a pas le droit d'ouvrir n'y figure pas.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::ok($this->menu->resolve($user, $this->organizationId()));
    }

    /**
     * Catalogue configurable de l'organisation active, avec l'état choisi.
     *
     * Permission requise : `organizations.update`. Régler le menu de son
     * organisation relève de son administration.
     */
    public function catalogue(Request $request): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('update', Organization::findOrFail($organizationId));

        return ApiResponse::ok($this->menu->catalogue($organizationId));
    }

    /**
     * Régler la visibilité et l'ordre des entrées.
     *
     * Permission requise : `organizations.update`.
     *
     * Une entrée marquée `alwaysVisible` dans le catalogue ne peut pas être
     * masquée : l'administration en fait partie, faute de quoi un organisme
     * pourrait se couper l'accès aux écrans qui permettent de revenir en
     * arrière. La demande est ignorée plutôt que refusée — la requête reste
     * valide, c'est la contrainte qui l'emporte.
     */
    public function update(UpdateMenuRequest $request): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $organization = Organization::findOrFail($organizationId);
        $this->authorize('update', $organization);

        $items = $request->validated('items');
        $before = $this->menu->catalogue($organizationId);

        DB::transaction(function () use ($items, $organizationId): void {
            foreach ($items as $item) {
                $entry = MenuCatalogue::find($item['code']);

                if ($entry === null || $entry->scope->value !== 'organization') {
                    continue;
                }

                OrganizationMenuItem::updateOrCreate(
                    ['organization_id' => $organizationId, 'code' => $entry->code],
                    [
                        'is_visible' => $entry->alwaysVisible ? true : ($item['isVisible'] ?? true),
                        'position' => $item['position'] ?? $entry->position,
                    ]
                );
            }
        });

        $after = $this->menu->catalogue($organizationId);
        $this->audit($request, $organizationId, 'menu_updated', $organization, ['menu' => $before], ['menu' => $after]);

        return ApiResponse::ok($after);
    }
}
