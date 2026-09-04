<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Pricing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Pricing\StorePriceRuleRequest;
use App\Http\Resources\Api\V1\Pricing\PriceRuleResource;
use App\Modules\Pricing\Models\PriceList;
use App\Modules\Pricing\Models\PriceRule;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Les règles d'un barème.
 *
 * Elles vivent sous leur liste : une règle hors barème ne s'applique à
 * personne, et l'API ne propose donc pas de création globale.
 *
 * Les conditions partent et reviennent avec la règle. Elles n'existent pas
 * sans elle, et les éditer séparément obligerait à trois appels pour une seule
 * pensée — « la livraison, entre 1144 et 4000 ».
 */
class PriceRuleController extends Controller
{
    /**
     * Ajouter une règle à un barème.
     *
     * Permission requise : `price_lists.update` — composer un barème, c'est le
     * modifier.
     */
    public function store(StorePriceRuleRequest $request, PriceList $priceList): JsonResponse
    {
        $this->guardList($priceList);
        $this->authorize('update', $priceList);

        $rule = DB::transaction(function () use ($request, $priceList): PriceRule {
            $rule = PriceRule::create([
                'price_list_id' => $priceList->id,
                'service_id' => $request->validated('serviceId'),
                'code' => $request->validated('code'),
                'name' => $request->validated('name'),
                'formula' => $request->validated('formula'),
                'priority' => $request->integer('priority', 100),
                'is_active' => $request->boolean('isActive', true),
            ]);

            $this->syncConditions($rule, $request->validated('conditions'));

            return $rule;
        });

        return ApiResponse::created(new PriceRuleResource($rule->load('conditions')));
    }

    /**
     * Modifier une règle.
     *
     * Permission requise : `price_lists.update`.
     */
    public function update(StorePriceRuleRequest $request, PriceRule $priceRule): JsonResponse
    {
        $priceList = $priceRule->priceList;
        $this->guardList($priceList);
        $this->authorize('update', $priceList);

        DB::transaction(function () use ($request, $priceRule): void {
            $priceRule->update(array_filter([
                'service_id' => $request->validated('serviceId'),
                'code' => $request->validated('code'),
                'name' => $request->validated('name'),
                'formula' => $request->validated('formula'),
                'priority' => $request->validated('priority'),
            ], static fn ($value): bool => $value !== null) + ($request->has('isActive')
                ? ['is_active' => $request->boolean('isActive')]
                : []));

            // `serviceId` nul est une valeur : il rend la regle generique, et
            // `array_filter` l'aurait mange.
            if ($request->has('serviceId') && $request->validated('serviceId') === null) {
                $priceRule->update(['service_id' => null]);
            }

            if ($request->has('conditions')) {
                $this->syncConditions($priceRule, $request->validated('conditions'));
            }
        });

        return ApiResponse::ok(new PriceRuleResource($priceRule->fresh()->load('conditions')));
    }

    /**
     * Supprimer une règle.
     *
     * Permission requise : `price_lists.update`. Les zones de matrice qui la
     * désignaient partent avec elle — une zone sans règle ne calcule rien.
     */
    public function destroy(PriceRule $priceRule): JsonResponse
    {
        $priceList = $priceRule->priceList;
        $this->guardList($priceList);
        $this->authorize('update', $priceList);

        $priceRule->delete();

        return ApiResponse::noContent();
    }

    /**
     * Remplace les conditions par celles fournies.
     *
     * Un remplacement plutôt qu'une fusion : l'écran envoie la liste telle
     * qu'elle doit être, et une condition retirée à l'écran doit disparaître.
     *
     * @param  array<int, array<string, string|null>>|null  $conditions
     */
    private function syncConditions(PriceRule $rule, ?array $conditions): void
    {
        $rule->conditions()->delete();

        foreach ($conditions ?? [] as $condition) {
            $rule->conditions()->create([
                'variable' => $condition['variable'],
                'operator' => $condition['operator'],
                'value_from' => $condition['valueFrom'],
                'value_to' => $condition['valueTo'] ?? null,
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
