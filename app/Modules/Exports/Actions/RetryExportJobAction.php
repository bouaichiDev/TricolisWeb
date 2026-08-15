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
 *
 * **La ligne est verrouillée** (`lockForUpdate`) — corrigé en Phase 10. Sans
 * verrou, `attempt_count + 1` était lu hors transaction : deux relances
 * simultanées lisaient toutes deux la même valeur et n'en écrivaient qu'un seul
 * incrément. Le compteur aurait alors sous-estimé le nombre de tentatives, ce
 * qui est exactement ce qu'il sert à mesurer. Le refus de relancer un export
 * déjà transmis est lui aussi revérifié sous verrou : sans cela, deux relances
 * concurrentes pouvaient passer le premier contrôle avant que l'une n'écrive.
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
            /** @var ExportJob $locked */
            $locked = ExportJob::whereKey($job->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->sent_at !== null) {
                throw ExportJobNotRetryable::alreadySent();
            }

            $before = $locked->only(['status', 'attempt_count', 'error_message']);

            $locked->update([
                'status' => $status,
                'attempt_count' => $locked->attempt_count + 1,
                'error_message' => null,
            ]);

            $this->audit->execute(
                $context->organizationId,
                $context->user,
                'export_job.retried',
                $locked,
                $before,
                $locked->fresh()->only(['status', 'attempt_count', 'error_message']),
                null,
                $context->ipAddress,
            );

            return $locked->fresh();
        });
    }
}
