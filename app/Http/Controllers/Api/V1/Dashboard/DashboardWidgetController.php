<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Models\RoleDashboardConfiguration;
use App\Shared\Dashboard\DashboardWidget;
use App\Shared\Dashboard\DashboardWidgetRegistry;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Le catalogue des widgets, tel que le code le livre.
 *
 * Rien de ce qui vient d'ici ne dépend d'un rôle : c'est la liste de ce qui
 * existe, avec la permission que chaque widget exige. `GET
 * /roles/{role}/dashboard` sert la même liste **enrichie** de l'état du rôle —
 * activé ou non, à quel rang, et si le rôle a bien la permission requise. C'est
 * celle-là que l'écran de réglage appelle, en un seul aller-retour.
 *
 * Ce que la réponse ne contient **jamais** : le nom d'une classe de résolveur,
 * une requête SQL, le nom d'un composant React. Un catalogue qui les rendrait
 * inviterait un client à s'en servir, et le jour où l'un d'eux serait
 * modifiable, ce serait une exécution arbitraire offerte à l'administrateur.
 *
 * Permission : `dashboard.configure`. La liste n'intéresse que qui règle un
 * rôle ; `dashboard.view` suffit à consulter son propre tableau de bord, où le
 * catalogue n'apparaît pas.
 */
class DashboardWidgetController extends Controller
{
    public function index(): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('configure', [RoleDashboardConfiguration::class, $organizationId]);

        return ApiResponse::ok(array_map(
            static fn (DashboardWidget $widget): array => [
                'key' => $widget->key,
                'labelKey' => $widget->labelKey(),
                'descriptionKey' => $widget->descriptionKey(),
                'category' => $widget->category->value,
                'type' => $widget->type->value,
                'size' => $widget->size->value,
                'requiredPermission' => $widget->requiredPermission,
                'defaultPosition' => $widget->defaultPosition,
                'defaultEnabled' => $widget->defaultEnabled,
            ],
            DashboardWidgetRegistry::all(),
        ));
    }
}
