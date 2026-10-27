<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\Import;

use App\Modules\Integrations\DTOs\ImportOutcome;
use App\Modules\Integrations\DTOs\ImportRun;
use App\Modules\Integrations\DTOs\ImportSummary;
use App\Modules\Integrations\Models\CustomerImportConfiguration;
use App\Modules\Integrations\Models\CustomerImportRun;
use App\Modules\Integrations\Services\Sources\ImportSourceKind;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;
use Throwable;

/**
 * Écrit l'historique des exécutions d'import (`customer_import_runs`).
 *
 * Une écriture d'historique ne doit jamais faire échouer un import déjà fait :
 * une erreur ici est signalée (`report`) puis ignorée.
 *
 * Ce qui est gardé d'une commande refusée : rang, référence externe, statut,
 * champs et messages — jamais le contenu de la ligne. Au-delà de
 * `MAX_ERRORS` commandes refusées, les suivantes ne sont que comptées.
 */
final class CustomerImportHistory
{
    public const int MAX_ERRORS = 200;

    /**
     * @param  array<string, scalar|null>  $context  `file`, `jobId`…
     */
    public function record(ImportRun $run, ImportSummary $summary, array $context = [], ?CarbonInterface $startedAt = null, bool $rolledBack = false): void
    {
        $counts = $summary->counts();
        $status = match (true) {
            $rolledBack => CustomerImportRun::REJECTED,
            $summary->isClean() => CustomerImportRun::COMPLETED,
            default => CustomerImportRun::COMPLETED_WITH_ERRORS,
        };

        $this->write([
            'customer_id' => $run->customerId(),
            'configuration_id' => $run->configuration->id,
            'channel' => $run->channel,
            'source' => $run->source->value,
            'status' => $status,
            'row_count' => $counts['rowCount'],
            'order_count' => $counts['orderCount'],
            // Annulé en bloc : rien n'a été écrit, quoi qu'en dise le bilan.
            'created_count' => $rolledBack ? 0 : $counts['created'],
            'updated_count' => $rolledBack ? 0 : $counts['updated'],
            'skipped_count' => $counts['skipped'],
            'invalid_count' => $counts['invalid'],
            'failed_count' => $counts['failed'],
            'errors' => $this->errors($summary),
            'triggered_by' => $run->channel === ImportRun::PULL ? null : $run->actor->id,
            ...$this->context($context, $startedAt),
        ]);
    }

    /**
     * Une exécution qui n'a rien pu traiter : connexion refusée, fichier
     * illisible, erreur technique. Le message est celui montré à l'exploitant
     * — jamais un secret ni une trace.
     *
     * @param  array<string, scalar|null>  $context
     */
    public function recordFailure(CustomerImportConfiguration $configuration, string $channel, string $message, ?string $triggeredBy = null, array $context = []): void
    {
        $this->write([
            'customer_id' => $configuration->customer_id,
            'configuration_id' => $configuration->id,
            'channel' => $channel,
            'source' => ImportSourceKind::orderSource($configuration)->value,
            'status' => CustomerImportRun::FAILED,
            'error_message' => Str::limit($message, 1000),
            'triggered_by' => $triggeredBy,
            ...$this->context($context, null),
        ]);
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    private function errors(ImportSummary $summary): ?array
    {
        $refused = array_values(array_filter($summary->outcomes, static fn (ImportOutcome $outcome): bool => ! $outcome->succeeded()));

        if ($refused === []) {
            return null;
        }

        return array_map(static fn (ImportOutcome $outcome): array => [
            'index' => $outcome->index,
            'reference' => $outcome->reference,
            'status' => $outcome->status,
            'errors' => $outcome->errors,
        ], array_slice($refused, 0, self::MAX_ERRORS));
    }

    /**
     * @param  array<string, scalar|null>  $context
     * @return array<string, mixed>
     */
    private function context(array $context, ?CarbonInterface $startedAt): array
    {
        return [
            'file_name' => isset($context['file']) ? Str::limit((string) $context['file'], 250, '') : null,
            'job_id' => isset($context['jobId']) ? (string) $context['jobId'] : null,
            'started_at' => $startedAt,
            'finished_at' => now(),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function write(array $attributes): void
    {
        try {
            CustomerImportRun::create($attributes);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
