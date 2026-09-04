<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Sources;

use App\Modules\Claims\Models\Claim;
use App\Modules\Dashboard\Services\DashboardContext;
use App\Modules\Dashboard\Services\DashboardDataSource;
use App\Modules\Dashboard\Services\DashboardPayload;
use App\Modules\Orders\Enums\OrderServiceStatus;
use App\Modules\Orders\Models\OrderService;
use App\Modules\ProofOfDelivery\Models\ProofOfDelivery;
use App\Shared\Database\MorphMap;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Réclamations et preuves de livraison.
 *
 * « Ouverte » se lit dans `closed_at`, jamais dans `status`. La colonne de
 * statut d'une réclamation est une chaîne libre que chaque organisme remplit à
 * sa façon : chercher les valeurs qui ressemblent à « ouvert » aurait donné un
 * compteur juste ici et faux ailleurs, sans que rien ne le signale. Une date de
 * clôture absente ne demande, elle, l'avis de personne.
 */
final readonly class ClaimsData implements DashboardDataSource
{
    /**
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    public function resolve(array $keys, DashboardContext $context): array
    {
        $data = [];

        foreach ($keys as $key) {
            $data[$key] = $this->resolveOne($key, $context);
        }

        return $data;
    }

    private function resolveOne(string $key, DashboardContext $context): mixed
    {
        return match ($key) {
            'open_claims' => DashboardPayload::alert(
                $this->claims($context)->whereNull('closed_at')->count()
            ),
            'claims_created_today' => DashboardPayload::kpi(
                $this->claims($context)->whereBetween('created_at', $context->dayBounds())->count()
            ),
            'recent_claims' => DashboardPayload::list($this->recentClaims($context)),

            // La preuve porte `delivered_at`, et aucune date de création : le
            // moment qui compte est celui de la remise, pas celui de la saisie.
            'pod_created_today' => DashboardPayload::kpi(
                ProofOfDelivery::query()
                    ->inOrganization($context->organizationId)
                    ->whereBetween('delivered_at', $context->dayBounds())
                    ->count()
            ),

            'services_without_pod' => DashboardPayload::alert($this->servicesWithoutProof($context)),

            'pod_coverage_rate' => $this->proofCoverage($context),

            default => null,
        };
    }

    /**
     * @return Builder<Claim>
     */
    private function claims(DashboardContext $context): Builder
    {
        return Claim::query()->where('organization_id', $context->organizationId);
    }

    /**
     * Services achevés dont personne n'a rapporté la preuve.
     *
     * Seuls les services **achevés** sont comptés : un service en cours n'a pas
     * encore de preuve à fournir, et l'inclure aurait fait grossir l'alerte au
     * rythme du travail en cours plutôt qu'à celui des oublis.
     *
     * Le `NOT EXISTS` s'appuie sur `proofs_of_delivery.order_service_id`, qui
     * est indexé. Charger les services puis retirer ceux qui ont une preuve
     * aurait demandé les deux tables entières en mémoire.
     */
    private function servicesWithoutProof(DashboardContext $context): int
    {
        return OrderService::query()
            ->where('status', OrderServiceStatus::COMPLETED->value)
            ->whereHas('order', fn (Builder $order) => $order->where('organization_id', $context->organizationId))
            ->whereNotExists(fn (QueryBuilder $proof) => $proof
                ->select(DB::raw('1'))
                ->from('proofs_of_delivery')
                ->whereColumn('proofs_of_delivery.order_service_id', 'order_services.id'))
            ->count();
    }

    /**
     * Le meme constat que `services_without_pod`, dans l'autre sens.
     *
     * Celui-la compte ce qui manque, celui-ci dit ou l'on en est. Les deux se
     * completent : un chiffre brut ne dit pas s'il est gros, un taux ne dit pas
     * combien de dossiers il represente. C'est pourquoi la jauge transporte la
     * part **et** le tout.
     *
     * @return array<string, mixed>
     */
    private function proofCoverage(DashboardContext $context): array
    {
        $completed = OrderService::query()
            ->where('status', OrderServiceStatus::COMPLETED->value)
            ->whereHas('order', fn (Builder $order) => $order->where('organization_id', $context->organizationId))
            ->count();

        return DashboardPayload::gauge($completed - $this->servicesWithoutProof($context), $completed);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentClaims(DashboardContext $context): array
    {
        return $this->claims($context)
            ->with('customer:id,name')
            ->orderByDesc('created_at')
            ->limit(6)
            ->get(['id', 'customer_id', 'title', 'status', 'created_at'])
            ->map(static fn (Claim $claim): array => [
                'id' => $claim->getKey(),
                'title' => $claim->getAttribute('title'),
                'subtitle' => $claim->getAttribute('customer')?->name,
                'status' => $claim->getAttribute('status'),
                'statusSource' => MorphMap::CLAIM,
                'date' => $claim->getAttribute('created_at')?->toIso8601String(),
                'route' => '/claims',
            ])
            ->all();
    }
}
