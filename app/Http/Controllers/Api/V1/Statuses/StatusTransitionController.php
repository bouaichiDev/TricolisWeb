<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Statuses;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Statuses\SyncStatusTransitionsRequest;
use App\Http\Resources\Api\V1\Statuses\StatusTransitionResource;
use App\Modules\Statuses\Models\Status;
use App\Modules\Statuses\Models\StatusTransition;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Transitions qui partent d'un statut.
 *
 * C'est ici que se dessine le cycle de vie. Jusqu'à cette phase il vivait dans
 * `OrderStatus::allowedTransitions()` ; le laisser dans le code à côté d'un
 * référentiel géré par l'administrateur revenait à tenir deux vérités.
 */
class StatusTransitionController extends Controller
{
    /**
     * Lister les transitions au départ d'un statut.
     *
     * Permission requise : `statuses.view`.
     */
    public function index(Status $status): JsonResponse
    {
        $this->authorize('view', $status);

        $transitions = $status->outgoing()->with('to')->get()
            ->sortBy(fn (StatusTransition $transition) => $transition->to?->position)
            ->values();

        return ApiResponse::ok(StatusTransitionResource::collection($transitions));
    }

    /**
     * Remplacer les transitions au départ d'un statut.
     *
     * Permission requise : `statuses.update`, réservée à la plateforme.
     *
     * L'ensemble est remplacé d'un bloc, dans une transaction : une mise à jour
     * arête par arête laisserait, le temps de la séquence, un graphe que
     * personne n'a voulu — et des commandes bloquées entre-temps.
     */
    public function sync(SyncStatusTransitionsRequest $request, Status $status): JsonResponse
    {
        $this->authorize('update', $status);

        $wanted = collect($request->validated('transitions'))
            ->keyBy(fn (array $transition): string => (string) $transition['toStatusId']);

        DB::transaction(function () use ($status, $wanted): void {
            $status->outgoing()->whereNotIn('to_status_id', $wanted->keys())->delete();

            foreach ($wanted as $toStatusId => $transition) {
                StatusTransition::updateOrCreate(
                    ['from_status_id' => $status->id, 'to_status_id' => (string) $toStatusId],
                    ['is_manual' => (bool) ($transition['isManual'] ?? true)],
                );
            }
        });

        $this->auditTransitions($request, $status);

        return $this->index($status->fresh());
    }

    /**
     * Le journal est indexé par organisation ; ce référentiel n'en a pas. La
     * trace est rattachée à l'organisation active de l'auteur, celle depuis
     * laquelle l'administrateur plateforme travaille.
     */
    private function auditTransitions(SyncStatusTransitionsRequest $request, Status $status): void
    {
        $organizationId = $this->organizationId();

        if ($organizationId === null) {
            return;
        }

        $this->audit($request, $organizationId, 'updated', $status, null, [
            'transitions' => $status->outgoing()->pluck('to_status_id')->all(),
        ]);
    }
}
