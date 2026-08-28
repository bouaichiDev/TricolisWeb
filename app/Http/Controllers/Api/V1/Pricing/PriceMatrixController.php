<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Pricing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Pricing\StorePriceMatrixRequest;
use App\Http\Resources\Api\V1\Pricing\PriceMatrixResource;
use App\Modules\Pricing\Models\PriceList;
use App\Modules\Pricing\Models\PriceMatrix;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Les matrices d'un barème.
 *
 * Une matrice choisit quelle règle appliquer selon une dimension — le code
 * postal aujourd'hui. Elle ne calcule pas : c'est la formule de la règle
 * désignée qui produit le prix.
 */
class PriceMatrixController extends Controller
{
    /**
     * Ajouter une matrice à un barème.
     *
     * Permission requise : `price_lists.update`.
     */
    public function store(StorePriceMatrixRequest $request, PriceList $priceList): JsonResponse
    {
        $this->guardList($priceList);
        $this->authorize('update', $priceList);

        $matrix = DB::transaction(function () use ($request, $priceList): PriceMatrix {
            $matrix = PriceMatrix::create([
                'price_list_id' => $priceList->id,
                'service_id' => $request->validated('serviceId'),
                'code' => $request->validated('code'),
                'name' => $request->validated('name'),
                'dimension' => $request->validated('dimension') ?? PriceMatrix::POSTAL_CODE,
                'is_active' => $request->boolean('isActive', true),
            ]);

            $this->syncRows($matrix, $request->validated('rows'));

            return $matrix;
        });

        return ApiResponse::created(new PriceMatrixResource($matrix->load('rows.priceRule')));
    }

    /**
     * Modifier une matrice et ses zones.
     *
     * Permission requise : `price_lists.update`.
     */
    public function update(StorePriceMatrixRequest $request, PriceMatrix $priceMatrix): JsonResponse
    {
        $priceList = $priceMatrix->priceList;
        $this->guardList($priceList);
        $this->authorize('update', $priceList);

        DB::transaction(function () use ($request, $priceMatrix): void {
            $priceMatrix->update(array_filter([
                'service_id' => $request->validated('serviceId'),
                'code' => $request->validated('code'),
                'name' => $request->validated('name'),
                'dimension' => $request->validated('dimension'),
            ], static fn ($value): bool => $value !== null) + ($request->has('isActive')
                ? ['is_active' => $request->boolean('isActive')]
                : []));

            if ($request->has('rows')) {
                $this->syncRows($priceMatrix, $request->validated('rows'));
            }
        });

        return ApiResponse::ok(new PriceMatrixResource($priceMatrix->fresh()->load('rows.priceRule')));
    }

    /**
     * Supprimer une matrice.
     *
     * Permission requise : `price_lists.update`. Ses règles restent : elles
     * redeviennent alors applicables directement, n'étant plus désignées par
     * aucune zone.
     */
    public function destroy(PriceMatrix $priceMatrix): JsonResponse
    {
        $priceList = $priceMatrix->priceList;
        $this->guardList($priceList);
        $this->authorize('update', $priceList);

        $priceMatrix->delete();

        return ApiResponse::noContent();
    }

    /**
     * Remplace les zones par celles fournies.
     *
     * L'écran envoie le barème tel qu'il doit être : une zone retirée à l'écran
     * doit disparaître, et une fusion laisserait traîner d'anciennes bornes.
     *
     * @param  array<int, array<string, string|int|null>>|null  $rows
     */
    private function syncRows(PriceMatrix $matrix, ?array $rows): void
    {
        $matrix->rows()->delete();

        foreach ($rows ?? [] as $row) {
            $matrix->rows()->create([
                'price_rule_id' => $row['priceRuleId'],
                'label' => $row['label'],
                'match_mode' => $row['matchMode'] ?? 'numeric',
                'range_from' => $row['rangeFrom'],
                'range_to' => $row['rangeTo'] ?? null,
                'priority' => $row['priority'] ?? 100,
            ]);
        }
    }

    private function guardList(?PriceList $priceList): void
    {
        abort_unless(
            $priceList !== null && $priceList->organization_id === $this->requireOrganizationId(),
            404,
            'Tarif introuvable.',
        );
    }
}
