<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tours;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTourScope;
use App\Http\Controllers\Controller;
use App\Modules\Planning\Services\RouteGeometryService;
use App\Modules\Tours\Models\Tour;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Le tracé routier d'une tournée.
 *
 * Seul, parce que c'est une lecture coûteuse et à part : elle interroge un
 * fournisseur distinct de celui des distances, et ne modifie rien.
 */
class TourRouteController extends Controller
{
    use ResolvesTourScope;

    /**
     * Le tracé routier de la tournée, entre ses arrêts.
     *
     * Permission requise : `tours.view`.
     *
     * **Aucune table.** Le §117 interdit une entité `RouteGeometry` : le tracé
     * se recalcule et ne vit qu'en cache, une heure, sous une clé qui dépend
     * des points eux-mêmes — réordonner les arrêts change la clé, donc le
     * tracé, sans qu'on ait à penser à l'invalider.
     *
     * Rend une liste vide quand aucun fournisseur n'est déclaré ou qu'un arrêt
     * n'est pas géocodé. L'écran retombe alors sur ses segments à vol d'oiseau,
     * et le dit — plutôt que de dessiner une route inventée, ce que le §101
     * interdit.
     */
    public function routeGeometry(Request $request, Tour $tour, RouteGeometryService $geometry): JsonResponse
    {
        $organizationId = $this->guardTour($tour);
        $this->authorize('view', $tour);

        $points = $tour->stops()->orderBy('sequence')->with('address')->get()
            ->map(fn ($stop): ?array => $stop->address?->latitude === null || $stop->address?->longitude === null
                ? null
                : [(float) $stop->address->latitude, (float) $stop->address->longitude])
            ->filter()
            ->values()
            ->all();

        if (count($points) < 2) {
            return ApiResponse::ok(['points' => []]);
        }

        $key = 'tour-route:'.$tour->id.':'.md5(json_encode($points));

        $trace = Cache::remember($key, now()->addHour(), fn (): array => $geometry->trace($points, $organizationId));

        return ApiResponse::ok(['points' => $trace]);
    }
}
