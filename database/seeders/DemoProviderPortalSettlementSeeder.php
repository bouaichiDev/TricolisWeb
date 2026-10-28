<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Providers\Models\Provider;
use App\Modules\ProviderSettlements\Actions\CalculateProviderSettlementLineTotal;
use App\Modules\ProviderSettlements\Actions\RecalculateProviderSettlementTotals;
use App\Modules\ProviderSettlements\Models\ProviderSettlement;
use App\Modules\ProviderSettlements\Models\ProviderSettlementLine;
use Database\Seeders\Support\ProviderDemoOrder;
use Illuminate\Database\Seeder;

/**
 * Un décompte clôturé et un brouillon pour Alpha, un décompte clôturé pour
 * Beta : de quoi vérifier dans les parcours Playwright qu'Alpha ne lit que
 * ses décomptes clôturés. Les totaux sont ceux du module financier
 * (`CalculateProviderSettlementLineTotal`, `RecalculateProviderSettlementTotals`),
 * jamais saisis ici.
 *
 * Local et test seulement ; rejouable. Appelé par `DemoProviderPortalTourSeeder`.
 */
class DemoProviderPortalSettlementSeeder extends Seeder
{
    /** @var array<string, array{0: string, 1: string, 2: string}> numéro => [fournisseur, statut, coût unitaire] */
    private const array SETTLEMENTS = [
        'DEMO-STL-ALPHA-1' => ['transports-alpha', 'closed', '80.00'],
        'DEMO-STL-ALPHA-2' => ['transports-alpha', 'draft', '55.00'],
        'DEMO-STL-BETA-1' => ['transports-beta', 'closed', '999.00'],
    ];

    public function run(CalculateProviderSettlementLineTotal $line, RecalculateProviderSettlementTotals $totals, ProviderDemoOrder $order): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        foreach (self::SETTLEMENTS as $number => [$code, $status, $unitCost]) {
            $provider = Provider::where('code', $code)->first();

            if ($provider === null) {
                continue;
            }

            $settlement = ProviderSettlement::firstOrCreate(
                ['organization_id' => $provider->organization_id, 'settlement_number' => $number],
                ['provider_id' => $provider->id, 'period_from' => now()->subMonth()->startOfMonth()->toDateString(),
                    'period_to' => now()->subMonth()->endOfMonth()->toDateString(), 'subtotal' => 0, 'tax_total' => 0, 'total' => 0, 'status' => $status],
            );

            if ($settlement->wasRecentlyCreated) {
                // Un service ne se règle qu'une fois (index unique) : seul le
                // décompte clôturé d'Alpha porte celui de sa commande de démo.
                $service = $number === 'DEMO-STL-ALPHA-1' ? $order->service($provider->organization) : null;
                ProviderSettlementLine::create([
                    'settlement_id' => $settlement->id, 'order_service_id' => $service?->id, 'description' => 'Livraison',
                    'quantity' => 3, 'unit_cost' => $unitCost, 'total_cost' => $line->execute('3', $unitCost),
                ]);
                $totals->execute($settlement);
            }
        }
    }
}
