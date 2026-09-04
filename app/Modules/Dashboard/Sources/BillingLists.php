<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Sources;

use App\Modules\Billing\Models\Invoice;
use App\Modules\Dashboard\Services\DashboardContext;
use App\Modules\ProviderSettlements\Models\ProviderSettlement;
use App\Shared\Database\MorphMap;

/**
 * Les deux listes de la facturation, séparées des compteurs.
 *
 * Ce ne sont pas les mêmes questions. Un compteur demande « combien », et tient
 * en une ligne de requête ; une liste demande « lesquelles », et doit choisir
 * quels champs voyagent, dans quel ordre, et vers quel écran chaque ligne mène.
 * Les six projections du catalogue prennent chacune une vingtaine de lignes,
 * et les laisser parmi les compteurs faisait de `BillingData` un fichier où
 * l'on ne trouvait plus rien.
 */
final readonly class BillingLists
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function invoices(DashboardContext $context): array
    {
        return Invoice::query()
            ->where('organization_id', $context->organizationId)
            ->with('customer:id,name')
            ->orderByDesc('created_at')
            ->limit(6)
            ->get(['id', 'customer_id', 'invoice_number', 'status', 'total', 'currency_code', 'invoice_date'])
            ->map(static fn (Invoice $invoice): array => [
                'id' => $invoice->getKey(),
                'title' => $invoice->getAttribute('invoice_number'),
                'subtitle' => $invoice->getAttribute('customer')?->name,
                'status' => $invoice->getAttribute('status'),
                'statusSource' => MorphMap::INVOICE,
                // Le montant part avec sa devise, toujours. Un total nu se
                // lirait dans la monnaie de celui qui regarde, et deux factures
                // de la même liste peuvent ne pas être libellées pareil.
                'amount' => (float) $invoice->getAttribute('total'),
                'currencyCode' => $invoice->getAttribute('currency_code'),
                'date' => $invoice->getAttribute('invoice_date')?->toDateString(),
                'route' => '/billing/invoices/'.$invoice->getKey(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function settlements(DashboardContext $context): array
    {
        return ProviderSettlement::query()
            ->where('organization_id', $context->organizationId)
            ->with('provider:id,name')
            ->orderByDesc('period_to')
            ->limit(6)
            ->get(['id', 'provider_id', 'settlement_number', 'status', 'total', 'period_to'])
            ->map(static fn (ProviderSettlement $settlement): array => [
                'id' => $settlement->getKey(),
                'title' => $settlement->getAttribute('settlement_number'),
                'subtitle' => $settlement->getAttribute('provider')?->name,
                'status' => $settlement->getAttribute('status'),
                'statusSource' => MorphMap::PROVIDER_SETTLEMENT,
                'date' => $settlement->getAttribute('period_to')?->toDateString(),
                'route' => '/billing/settlements/'.$settlement->getKey(),
            ])
            ->all();
    }
}
