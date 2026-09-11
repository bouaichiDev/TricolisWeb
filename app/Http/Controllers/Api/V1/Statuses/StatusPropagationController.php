<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Statuses;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Statuses\StoreStatusPropagationRequest;
use App\Http\Requests\Api\V1\Statuses\UpdateStatusPropagationRequest;
use App\Http\Resources\Api\V1\Statuses\StatusPropagationResource;
use App\Modules\Statuses\Models\Status;
use App\Modules\Statuses\Models\StatusPropagation;
use App\Modules\Statuses\Services\StatusRelations;
use App\Shared\Http\Responses\ApiResponse;
use App\Shared\Support\InputMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ce qu'un statut entraîne sur les entités voisines.
 *
 * Les règles sont peu nombreuses et se lisent ensemble — « quand tout est
 * chargé, le service l'est » n'a de sens qu'à côté de « quand le service est
 * effectué, ses colis le sont ». La liste n'est donc pas paginée.
 *
 * Tout membre les lit : elles expliquent pourquoi une commande a changé de
 * statut toute seule. Seule la plateforme les écrit, comme le reste du
 * référentiel.
 */
final class StatusPropagationController extends Controller
{
    /**
     * Colonne de base de données → champ d'API.
     *
     * @var array<string, string>
     */
    private const array MAPPING = [
        'from_status_id' => 'fromStatusId',
        'to_status_id' => 'toStatusId',
        'mode' => 'mode',
        'active' => 'active',
    ];

    /**
     * Lister les règles de propagation.
     *
     * Permission requise : `statuses.view`.
     */
    public function index(StatusRelations $relations): JsonResponse
    {
        $this->authorize('viewAny', Status::class);

        $rules = StatusPropagation::with(['fromStatus', 'toStatus'])->get()
            ->sortBy([
                fn (StatusPropagation $rule): string => $rule->fromStatus?->source ?? '',
                fn (StatusPropagation $rule): int => $rule->fromStatus?->position ?? 0,
            ])
            ->values();

        // La hiérarchie accompagne les règles : l'écran ne propose que des paires
        // qui mènent quelque part, sans avoir à redécrire le modèle.
        return ApiResponse::ok([
            'rules' => StatusPropagationResource::collection($rules)->resolve(),
            'hierarchy' => $relations->hierarchy(),
        ]);
    }

    /**
     * Déclarer une règle.
     *
     * Permission requise : `statuses.create`, réservée à la plateforme. Les deux
     * statuts doivent appartenir à des entités reliées — une commande et ses
     * colis, un service et ses colis : `StatusRelations` en décide.
     */
    public function store(StoreStatusPropagationRequest $request): JsonResponse
    {
        $this->authorize('create', Status::class);

        $rule = StatusPropagation::create(InputMapper::map($request->validated(), self::MAPPING));
        $this->auditRule($request, 'created', $rule, null, $rule->toArray());

        // `refresh` : `mode` et `active` viennent des valeurs par défaut de la
        // table quand la requête ne les porte pas.
        return ApiResponse::created(new StatusPropagationResource($rule->refresh()->load(['fromStatus', 'toStatus'])));
    }

    /**
     * Modifier une règle — son mode, ou son activation.
     *
     * Permission requise : `statuses.update`, réservée à la plateforme. La paire
     * elle-même se corrige : une règle mal visée se répare, elle n'a pas à être
     * supprimée puis resaisie.
     */
    public function update(UpdateStatusPropagationRequest $request, StatusPropagation $statusPropagation): JsonResponse
    {
        $this->authorize('update', new Status);

        $old = $statusPropagation->toArray();
        $statusPropagation->update(InputMapper::map($request->validated(), self::MAPPING));
        $this->auditRule($request, 'updated', $statusPropagation, $old, $statusPropagation->toArray());

        return ApiResponse::ok(new StatusPropagationResource(
            $statusPropagation->refresh()->load(['fromStatus', 'toStatus']),
        ));
    }

    /**
     * Supprimer une règle.
     *
     * Permission requise : `statuses.delete`, réservée à la plateforme. Rien
     * n'est défait : les statuts déjà propagés restent ce qu'ils sont.
     */
    public function destroy(Request $request, StatusPropagation $statusPropagation): JsonResponse
    {
        $this->authorize('delete', new Status);

        $snapshot = $statusPropagation->toArray();
        $statusPropagation->delete();
        $this->auditRule($request, 'deleted', $statusPropagation, $snapshot, null);

        return ApiResponse::noContent();
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    private function auditRule(Request $request, string $action, StatusPropagation $rule, ?array $oldValues, ?array $newValues): void
    {
        $organizationId = $this->organizationId();

        if ($organizationId !== null) {
            $this->audit($request, $organizationId, $action, $rule, $oldValues, $newValues);
        }
    }
}
