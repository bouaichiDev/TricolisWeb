<?php

declare(strict_types=1);

namespace App\Modules\Exports\Services;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Exports\Jobs\ProcessExportJob;
use App\Modules\Exports\Models\CustomerExportConfiguration;
use App\Modules\Exports\Models\ExportJob;
use App\Modules\Exports\Services\Content\OrderStatusExportContent;
use App\Modules\Orders\Events\OrderStatusChanged;
use App\Shared\Database\MorphMap;

/**
 * Pousse le statut d'une commande vers les systèmes clients qui l'ont
 * demandé — REST Push générique, quel que soit le client.
 *
 * Écoute `OrderStatusChanged` ; retient les configurations actives du client
 * de la commande dont `exportType = order_status` et
 * `frequency = on_order_status_changed` ; crée un `ExportJob` par
 * configuration et le met en file **après commit**. Jamais d'appel réseau
 * dans la transaction du changement de statut.
 *
 * **Pas de doublon en attente** : si un envoi de cette commande vers cette
 * destination n'est pas encore parti, il n'en est pas créé un second — celui
 * qui attend transmettra le statut courant.
 */
final readonly class OrderStatusExportTrigger
{
    public function __construct(private WriteAuditLog $audit) {}

    public function handle(OrderStatusChanged $event): void
    {
        $order = $event->order;

        $configurations = CustomerExportConfiguration::where('customer_id', $order->customer_id)
            ->where('is_active', true)
            ->where('export_type', OrderStatusExportContent::EXPORT_TYPE)
            ->where('frequency', OrderStatusExportContent::ON_STATUS_CHANGED)
            ->orderBy('name')
            ->get();

        foreach ($configurations as $configuration) {
            $pending = ExportJob::where('configuration_id', $configuration->id)
                ->where('entity_type', MorphMap::ORDER)
                ->where('entity_id', $order->id)
                ->whereNull('sent_at')
                ->whereIn('status', ['pending', 'processing'])
                ->exists();

            if ($pending) {
                continue;
            }

            $job = ExportJob::create([
                'customer_id' => $configuration->customer_id,
                'configuration_id' => $configuration->id,
                'entity_type' => MorphMap::ORDER,
                'entity_id' => $order->id,
                'status' => 'pending',
                'attempt_count' => 0,
            ]);

            $this->audit->execute(
                $event->context->organizationId,
                $event->context->user,
                'export_job.created',
                $job,
                null,
                ['configurationId' => $configuration->id, 'entityType' => MorphMap::ORDER, 'trigger' => OrderStatusExportContent::ON_STATUS_CHANGED],
                null,
                $event->context->ipAddress,
            );

            ProcessExportJob::dispatch($job->id)->afterCommit();
        }
    }
}
