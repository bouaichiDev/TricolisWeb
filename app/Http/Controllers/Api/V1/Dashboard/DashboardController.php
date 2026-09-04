<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Controller;
use App\Modules\Dashboard\Services\DashboardComposer;
use App\Modules\Identity\Models\RoleDashboardConfiguration;
use App\Modules\Identity\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Le tableau de bord de l'appelant.
 *
 * **Un seul appel.** Le tableau de bord précédent demandait une page d'un
 * élément à quatre listes paginées pour n'en lire que le total : quatre
 * requêtes HTTP, quatre autorisations, quatre paginations, pour quatre entiers.
 * Avec une cinquantaine de widgets disponibles, la même méthode aurait fini par
 * ouvrir une requête par carte.
 *
 * Ce que le serveur renvoie est **déjà filtré**. Un widget que l'appelant n'a
 * pas le droit de voir n'est pas dans la réponse — pas masqué, pas mis à
 * `null` : absent, et son chiffre n'a même pas été calculé. C'est la seule
 * façon d'empêcher qu'un onglet réseau ouvert donne ce que l'écran refuse.
 *
 * Permission : `dashboard.view`. Elle ouvre l'écran, elle ne décide de rien de
 * ce qu'on y trouve — cela dépend des rôles de l'appelant et de leurs propres
 * permissions.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly DashboardComposer $composer) {}

    public function index(Request $request): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('viewAny', [RoleDashboardConfiguration::class, $organizationId]);

        /** @var User $user */
        $user = $request->user();

        // L'organisation est rappelée dans la réponse, et pas seulement parce
        // qu'elle s'affiche en tête d'écran : elle dit **de quelle**
        // organisation ces chiffres viennent. Un utilisateur qui en change en
        // gardant l'ancien tableau de bord à l'écran verrait sinon des totaux
        // sans savoir à qui les rattacher.
        $organization = Organization::query()->find($organizationId, ['id', 'name']);

        return ApiResponse::ok([
            'organization' => $organization === null ? null : [
                'id' => $organization->getKey(),
                'name' => $organization->getAttribute('name'),
            ],
            'widgets' => $this->composer->compose($user, $organizationId),
        ]);
    }
}
