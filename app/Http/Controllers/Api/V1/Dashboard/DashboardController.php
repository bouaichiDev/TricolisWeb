<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Dashboard\ShowDashboardRequest;
use App\Modules\Dashboard\Services\DashboardComposer;
use App\Modules\Identity\Models\RoleDashboardConfiguration;
use App\Modules\Identity\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

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
 * `from` et `to` restreignent les cartes qui portent une date — et **elles
 * seules**. Le tableau de bord répond d'abord à « où en est-on », ce qui n'a pas
 * d'intervalle : filtrer « commandes à planifier » sur le mois d'août rendrait
 * un chiffre sans signification. Le catalogue dit widget par widget qui suit la
 * période, la réponse le répète dans `periodAware`, et l'écran range les cartes
 * en deux sections plutôt que de laisser deviner ce que le filtre a touché.
 *
 * Permission : `dashboard.view`. Elle ouvre l'écran, elle ne décide de rien de
 * ce qu'on y trouve — cela dépend des rôles de l'appelant et de leurs propres
 * permissions.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly DashboardComposer $composer) {}

    public function index(ShowDashboardRequest $request): JsonResponse
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

        $period = $request->period();

        return ApiResponse::ok([
            'organization' => $organization === null ? null : [
                'id' => $organization->getKey(),
                'name' => $organization->getAttribute('name'),
            ],
            // La période est rappelée dans la réponse, comme l'organisation :
            // elle dit **sur quoi** ces chiffres ont été calculés. Sans elle,
            // un écran rouvert sur des données mises en cache afficherait des
            // totaux de période sous un filtre vide, et rien ne l'indiquerait.
            'period' => $period?->toArray(),
            'widgets' => $this->composer->compose($user, $organizationId, $period),
        ]);
    }
}
