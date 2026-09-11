<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Statuses;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Statuses\SetDefaultStatusRequest;
use App\Modules\Statuses\Actions\SetDefaultStatus;
use App\Modules\Statuses\Models\Status;
use App\Modules\Statuses\Services\DefaultStatuses;
use App\Modules\Statuses\Services\StatusSources;
use App\Shared\Database\MorphMap;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;

/**
 * Statut par défaut de chaque entité, à la création.
 *
 * La liste des entités n'est écrite nulle part : c'est celle du référentiel,
 * dérivée de la morph map et des tables qui portent une colonne `status`. Une
 * entité ajoutée au code apparaît ici d'elle-même.
 */
final class StatusDefaultController extends Controller
{
    /**
     * Lister, pour chaque entité, son statut par défaut et ceux qu'on peut choisir.
     *
     * Permission requise : `statuses.view`. `systemDefault` est la valeur que la
     * colonne prend faute de choix — ce que l'écran affiche quand rien n'est coché.
     */
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Status::class);

        $statuses = Status::orderBy('position')->orderBy('status')->get()->groupBy('source');

        $rows = array_map(function (string $source) use ($statuses): array {
            $options = $statuses->get($source, collect());
            $chosen = $options->first(fn (Status $status): bool => $status->is_default && $status->active);

            return [
                'source' => $source,
                'statusId' => $chosen?->id,
                'code' => $chosen?->code,
                'systemDefault' => $this->systemDefault($source),
                'options' => $options->map(fn (Status $status): array => [
                    'id' => $status->id,
                    'code' => $status->code,
                    'label' => $status->label,
                    'active' => $status->active,
                ])->values()->all(),
            ];
        }, StatusSources::all());

        return ApiResponse::ok($rows);
    }

    /**
     * Choisir le statut par défaut d'une entité, ou retirer ce choix (`null`).
     *
     * Permission requise : `statuses.update`, réservée à la plateforme.
     */
    public function update(SetDefaultStatusRequest $request, string $source, SetDefaultStatus $action): JsonResponse
    {
        abort_unless(StatusSources::supports($source), 404);

        $this->authorize('update', new Status(['source' => $source]));

        $changed = $action->execute($source, $request->validated('statusId'));
        $organizationId = $this->organizationId();

        foreach ($organizationId === null ? [] : $changed as $status) {
            $this->audit($request, $organizationId, 'updated', $status,
                ['is_default' => ! $status->is_default], ['is_default' => $status->is_default]);
        }

        return $this->index();
    }

    private function systemDefault(string $source): ?string
    {
        $class = MorphMap::class($source);

        if ($class === null || ! is_subclass_of($class, Model::class)) {
            return null;
        }

        return DefaultStatuses::columnDefault((new $class)->getTable());
    }
}
