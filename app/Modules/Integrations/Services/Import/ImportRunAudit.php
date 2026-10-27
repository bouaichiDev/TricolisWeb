<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\Import;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Integrations\DTOs\ImportRun;
use App\Modules\Integrations\DTOs\ImportSummary;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;

/**
 * La trace d'une exécution d'import : une entrée d'audit sur la
 * configuration, une ligne de journal structurée.
 *
 * L'audit garde **les compteurs**, l'origine, le canal, le fichier — jamais
 * une donnée client (nom, adresse, contenu d'une ligne), ni un secret.
 * L'exécution est aussi écrite dans l'historique (`CustomerImportHistory`,
 * depuis le 2026-10-10), avec les commandes refusées et leurs messages.
 *
 * Le journal porte les identifiants de corrélation demandés au §47 :
 * client, configuration, identifiant de job de file s'il existe.
 */
final readonly class ImportRunAudit
{
    public const string ACTION = 'customer_import_configuration.executed';

    public function __construct(
        private WriteAuditLog $audit,
        private CustomerImportHistory $history,
    ) {}

    /**
     * @param  array<string, scalar|null>  $context  file, jobId… — jamais de secret
     */
    public function record(ImportRun $run, ImportSummary $summary, array $context = [], ?CarbonInterface $startedAt = null): void
    {
        $this->history->record($run, $summary, $context, $startedAt);

        $values = [
            ...$summary->counts(),
            'channel' => $run->channel,
            'source' => $run->source->value,
            ...$context,
        ];

        $this->audit->execute(
            $run->organizationId,
            $run->actor,
            self::ACTION,
            $run->configuration,
            null,
            $values,
            null,
            $run->request?->ip(),
        );

        Log::info('customer-integration.import', [
            'customerId' => $run->customerId(),
            'configurationId' => $run->configuration->id,
            ...$values,
        ]);
    }
}
