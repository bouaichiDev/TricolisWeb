<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Sources;

use App\Modules\Billing\Models\Invoice;
use App\Modules\Dashboard\Services\DashboardContext;
use App\Modules\Dashboard\Services\DashboardDataSource;
use App\Modules\Dashboard\Services\DashboardPayload;
use App\Modules\Orders\Enums\OrderServiceStatus;
use App\Modules\Orders\Models\OrderService;
use App\Modules\ProviderSettlements\Models\ProviderSettlement;
use App\Shared\Database\MorphMap;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Facturation et décomptes fournisseurs.
 *
 * **Aucun total unique.** Les factures portent `currency_code`, et un organisme
 * qui facture en trois monnaies verrait, dans une tuile, la somme des trois —
 * un nombre sans signification, mais qui ressemble assez à un chiffre
 * d'affaires pour qu'on le lise sans le vérifier. Le total du mois est donc un
 * graphe, une barre par devise, et chacune se lit pour ce qu'elle est.
 *
 * Le reste compte des lignes, ce qui s'additionne sans rien supposer.
 */
final readonly class BillingData implements DashboardDataSource
{
    /** Ce que la clôture écrit dans `invoices.status`. */
    private const string CLOSED = 'closed';

    private const string DRAFT = 'draft';

    public function __construct(private BillingLists $lists) {}

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
            'prebilling_services' => DashboardPayload::kpi($this->billableServices($context)),

            'draft_invoices' => DashboardPayload::kpi(
                $this->invoices($context)->where('status', self::DRAFT)->count()
            ),
            'closed_invoices_today' => DashboardPayload::kpi(
                $this->invoices($context)
                    ->where('status', self::CLOSED)
                    ->whereBetween('created_at', $context->dayBounds())
                    ->count()
            ),
            // `amounts`, et non `chart` : une devise par ligne, sans barre
            // proportionnelle. Deux montants libellés dans deux monnaies ne se
            // rangent pas sur la même règle, et une longueur le laisserait
            // pourtant croire.
            'closed_invoices_period_total' => DashboardPayload::amounts($this->totalsByCurrency($context)),
            'invoices_by_status' => DashboardPayload::chart($this->invoicesByStatus($context), MorphMap::INVOICE),
            'recent_invoices' => DashboardPayload::list($this->lists->invoices($context)),

            'draft_provider_settlements' => DashboardPayload::kpi(
                ProviderSettlement::query()
                    ->where('organization_id', $context->organizationId)
                    ->where('status', self::DRAFT)
                    ->count()
            ),
            'recent_provider_settlements' => DashboardPayload::list($this->lists->settlements($context)),

            default => null,
        };
    }

    /**
     * @return Builder<Invoice>
     */
    private function invoices(DashboardContext $context): Builder
    {
        return Invoice::query()->where('organization_id', $context->organizationId);
    }

    /**
     * Services achevés qui n'ont pas encore de ligne de facture.
     *
     * C'est ce que l'écran de préfacturation propose, et la même définition :
     * un service achevé sans `invoice_lines.order_service_id` correspondant.
     * En choisir une autre ici aurait annoncé un travail que l'écran ne montre
     * pas.
     */
    private function billableServices(DashboardContext $context): int
    {
        return OrderService::query()
            ->where('status', OrderServiceStatus::COMPLETED->value)
            ->whereHas('order', fn (Builder $order) => $order->where('organization_id', $context->organizationId))
            ->whereNotExists(fn (QueryBuilder $line) => $line
                ->select(DB::raw('1'))
                ->from('invoice_lines')
                ->whereColumn('invoice_lines.order_service_id', 'order_services.id'))
            ->count();
    }

    /**
     * Total des factures closes du mois, **par devise**.
     *
     * Le mois plutôt qu'une période réglable : la période serait un paramètre
     * de plus à porter dans la configuration, et le tableau de bord répond à
     * « où en est-on », pas à « combien exactement entre telle et telle date »
     * — c'est le rôle de la liste des factures, qui sait filtrer.
     *
     * @return array<int, array{code: string, value: float}>
     */
    private function totalsByCurrency(DashboardContext $context): array
    {
        return $this->invoices($context)
            ->toBase()
            ->where('status', self::CLOSED)
            ->whereBetween('invoice_date', [
                $context->today->startOfMonth()->toDateString(),
                $context->today->endOfMonth()->toDateString(),
            ])
            ->selectRaw('currency_code, SUM(total) as amount')
            ->groupBy('currency_code')
            ->orderBy('currency_code')
            ->get()
            ->map(static fn (object $row): array => [
                'code' => (string) $row->currency_code,
                'value' => (float) $row->amount,
            ])
            ->all();
    }

    /**
     * @return array<int, array{code: string, value: int}>
     */
    private function invoicesByStatus(DashboardContext $context): array
    {
        return $this->invoices($context)
            ->toBase()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->map(static fn (object $row): array => [
                'code' => (string) $row->status,
                'value' => (int) $row->total,
            ])
            ->all();
    }
}
