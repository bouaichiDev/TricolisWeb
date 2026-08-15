<?php

declare(strict_types=1);

namespace App\Modules\Exports\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Exports\Exceptions\ExportJobNotRetryable;
use App\Modules\Exports\Models\ExportJob;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Relance un export.
 *
 * Le §27 autorise cette route « uniquement sans créer de nouvelles colonnes ».
 * Elle n'en crée aucune : `attemptCount` est incrémenté, `errorMessage` effacé,
 * `sentAt` remis à zéro. Le statut est **fourni** par l'appelant, le diagramme
 * n'en énumérant aucun.
 *
 * Un export déjà transmis n'est pas relançable : `sentAt` renseigné signifie que
 * le client a reçu le fichier. Le renvoyer produirait un doublon chez lui.
 */
final readonly class RetryExportJobAction
{
    public function __construct(private WriteAuditLog $audit) {}

    public function execute(ExportJob $job, string $status, AuditContext $context): ExportJob
    {
        if ($job->sent_at !== null) {
            throw ExportJobNotRetryable::alreadySent();
        }

        return DB::transaction(function () use ($job, $status, $context): ExportJob {
            $before = $job->only(['status', 'attempt_count', 'error_message']);

            $job->update([
                'status' => $status,
                'attempt_count' => $job->attempt_count + 1,
                'error_message' => null,
            ]);

            $this->audit->execute(
                $context->organizationId,
                $context->user,
                'export_job.retried',
                $job,
                $before,
                $job->fresh()->only(['status', 'attempt_count', 'error_message']),
                null,
                $context->ipAddress,
            );

            return $job->fresh();
        });
    }
}
