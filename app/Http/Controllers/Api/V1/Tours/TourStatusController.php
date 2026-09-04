<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tours;

use App\Http\Controllers\Api\V1\Concerns\BuildsAuditContext;
use App\Http\Controllers\Api\V1\Concerns\ResolvesTourScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Tours\ChangeTourStatusRequest;
use App\Http\Resources\Api\V1\Tours\TourDetailResource;
use App\Modules\Planning\Actions\ChangeTourStatus;
use App\Modules\Tours\Models\Tour;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Faire changer une tournée d'état.
 *
 * Séparé de la composition : verser des commandes et déclarer une tournée
 * confirmée sont deux décisions différentes, et le 28 août 2026 le propriétaire
 * du projet a demandé qu'elles ne se confondent surtout pas — confirmer ses
 * modifications sur la carte ne doit pas confirmer la tournée.
 */
class TourStatusController extends Controller
{
    use BuildsAuditContext;
    use ResolvesTourScope;

    /**
     * Faire passer une tournée d'un état à un autre.
     *
     * Permission requise : `tours.update`. C'est par ici que se valide un
     * brouillon et qu'il s'annule : le référentiel dit quels passages
     * existent, et l'action les applique dans une transaction, tournée
     * verrouillée.
     *
     * Un brouillon reste réservé à celui qui le prépare, comme toute autre
     * écriture.
     */
    public function changeStatus(
        ChangeTourStatusRequest $request,
        Tour $tour,
        ChangeTourStatus $action,
    ): JsonResponse {
        $organizationId = $this->guardTour($tour);
        $this->guardDraftOwner($tour);
        $this->authorize('update', $tour);

        $updated = $action->execute(
            $tour,
            $request->validated('status'),
            $this->auditContext($request, $organizationId),
        );

        return ApiResponse::ok(new TourDetailResource($updated));
    }
}
