<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Services\Integrations;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Exports\Models\ExportJob;
use App\Modules\Integrations\Services\Import\ImportRunAudit;
use App\Modules\Orders\Models\Order;
use App\Shared\Database\MorphMap;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * La « dernière activité » d'une intégration, lue dans ce qui existe déjà :
 * l'audit des exécutions d'import, les dates d'envoi des `ExportJob`.
 *
 * Une requête groupée par liste — jamais une par ligne affichée.
 */
final readonly class CustomerIntegrationActivity
{
    /**
     * @param  list<string>  $configurationIds
     * @return array<string, string> identifiant → date ISO de la dernière exécution
     */
    public function lastImportRuns(array $configurationIds): array
    {
        if ($configurationIds === []) {
            return [];
        }

        return AuditLog::query()
            ->where('entity_type', MorphMap::CUSTOMER_IMPORT_CONFIGURATION)
            ->whereIn('entity_id', $configurationIds)
            ->where('action', ImportRunAudit::ACTION)
            ->groupBy('entity_id')
            ->selectRaw('entity_id, MAX(created_at) as last_run')
            ->pluck('last_run', 'entity_id')
            ->map(static fn (mixed $date): string => Carbon::parse((string) $date)->toIso8601String())
            ->all();
    }

    /**
     * @param  list<string>  $configurationIds
     * @return array<string, string> identifiant → date ISO du dernier envoi réussi
     */
    public function lastExportsSent(array $configurationIds): array
    {
        if ($configurationIds === []) {
            return [];
        }

        return ExportJob::query()
            ->whereIn('configuration_id', $configurationIds)
            ->whereNotNull('sent_at')
            ->groupBy('configuration_id')
            ->selectRaw('configuration_id, MAX(sent_at) as last_sent')
            ->pluck('last_sent', 'configuration_id')
            ->map(static fn (mixed $date): string => Carbon::parse((string) $date)->toIso8601String())
            ->all();
    }

    /**
     * La référence lisible de l'entité de chaque envoi (numéro de commande ou
     * de facture), en deux requêtes au plus.
     *
     * @param  Collection<int, ExportJob>  $jobs
     * @return array<string, string> identifiant d'entité → référence
     */
    public function entityReferences(Collection $jobs): array
    {
        $ids = static fn (string $type): array => $jobs->where('entity_type', $type)->pluck('entity_id')->filter()->unique()->values()->all();
        $orders = $ids(MorphMap::ORDER);
        $invoices = $ids(MorphMap::INVOICE);

        return [
            ...($orders === [] ? [] : Order::whereIn('id', $orders)->pluck('order_number', 'id')->all()),
            ...($invoices === [] ? [] : Invoice::whereIn('id', $invoices)->pluck('invoice_number', 'id')->all()),
        ];
    }
}
