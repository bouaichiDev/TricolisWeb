<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Pricing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Pricing\StorePricingVariableRequest;
use App\Http\Resources\Api\V1\Pricing\PricingVariableResource;
use App\Modules\Pricing\Models\PriceRule;
use App\Modules\Pricing\Models\PriceRuleCondition;
use App\Modules\Pricing\Models\PricingVariable;
use App\Modules\Pricing\Services\PricingVariableSources;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Le catalogue des variables tarifaires.
 *
 * **Lu par tous, écrit par la plateforme.** Un administrateur d'organisme en a
 * besoin pour écrire ses barèmes ; le laisser en créer ferait qu'une même
 * formule ne voudrait plus dire la même chose d'un organisme à l'autre, et
 * ouvrirait le choix de la source — c'est-à-dire des colonnes de la base.
 */
class PricingVariableController extends Controller
{
    /**
     * Lister les variables.
     *
     * Permission requise : `pricing_variables.view`. Toutes sont rendues, y
     * compris les inactives : l'écran de la plateforme doit les voir pour les
     * réactiver, et celui des organismes filtre sur `isActive`.
     */
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', PricingVariable::class);

        return ApiResponse::ok(PricingVariableResource::collection(
            PricingVariable::query()->orderBy('position')->orderBy('code')->get(),
        ));
    }

    /**
     * Les sources qu'une variable peut lire.
     *
     * Permission requise : `pricing_variables.manage`. C'est la liste que le
     * superadmin choisit ; elle vient du code, seul à savoir aller de la
     * prestation jusqu'à la valeur.
     */
    public function sources(): JsonResponse
    {
        $this->authorize('create', PricingVariable::class);

        return ApiResponse::ok(array_map(
            static fn (array $source, string $key): array => [
                'key' => $key,
                'table' => $source['table'],
                'column' => $source['column'],
                'kind' => $source['kind'],
                'label' => $source['label'],
            ],
            PricingVariableSources::all(),
            array_keys(PricingVariableSources::all()),
        ));
    }

    /**
     * Ajouter une variable.
     *
     * Permission requise : `pricing_variables.manage`, et portée plateforme.
     */
    public function store(StorePricingVariableRequest $request): JsonResponse
    {
        $this->authorize('create', PricingVariable::class);

        $variable = PricingVariable::create($this->attributes($request));

        return ApiResponse::created(new PricingVariableResource($variable));
    }

    /**
     * Modifier une variable.
     *
     * Le **code** reste modifiable, mais il est le nom écrit dans les formules :
     * le changer casserait celles qui l'emploient. Le contrôleur refuse donc de
     * renommer une variable déjà utilisée, plutôt que de laisser découvrir la
     * panne à la prochaine facture.
     */
    public function update(StorePricingVariableRequest $request, PricingVariable $pricingVariable): JsonResponse
    {
        $this->authorize('update', $pricingVariable);

        $code = $request->validated('code');

        if ($code !== null && $code !== $pricingVariable->code) {
            $this->refuseIfUsed($pricingVariable, 'code');
        }

        $pricingVariable->update($this->attributes($request, $pricingVariable));

        return ApiResponse::ok(new PricingVariableResource($pricingVariable->fresh()));
    }

    /**
     * Supprimer une variable.
     *
     * Refusée si une formule ou une condition l'emploie : la retirer laisserait
     * des barèmes qui ne calculent plus, et l'erreur n'apparaîtrait qu'au
     * moment de facturer.
     */
    public function destroy(PricingVariable $pricingVariable): JsonResponse
    {
        $this->authorize('delete', $pricingVariable);

        $this->refuseIfUsed($pricingVariable, 'variable');

        $pricingVariable->delete();

        return ApiResponse::noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(StorePricingVariableRequest $request, ?PricingVariable $existing = null): array
    {
        $sourceKey = $request->validated('sourceKey') ?? $existing?->source_key;

        return array_filter([
            'code' => $request->validated('code'),
            'label' => $request->validated('label'),
            'description' => $request->validated('description'),
            'source_key' => $sourceKey,
            // Le genre suit la source : un code postal ne devient pas
            // multipliable parce qu'on le déclarerait numérique.
            'kind' => PricingVariableSources::all()[$sourceKey]['kind'] ?? PricingVariableSources::NUMERIC,
            'unit' => $request->validated('unit'),
            'position' => $request->validated('position'),
        ], static fn ($value): bool => $value !== null)
            + ($request->has('isActive') ? ['is_active' => $request->boolean('isActive')] : []);
    }

    private function refuseIfUsed(PricingVariable $variable, string $field): void
    {
        $inFormula = PriceRule::where('formula', 'like', '%{P:'.$variable->code.'}%')->exists();
        $inCondition = PriceRuleCondition::where('variable', $variable->code)->exists();

        abort_if(
            $inFormula || $inCondition,
            422,
            sprintf('La variable « %s » est employée par un barème.', $variable->code),
        );
    }
}
