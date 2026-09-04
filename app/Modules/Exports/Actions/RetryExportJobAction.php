<?php

declare(strict_types=1);

namespace App\Modules\Exports\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Exports\Exceptions\ExportJobNotRetryable;
use App\Modules\Exports\Jobs\ProcessExportJob;
use App\Modules\Exports\Models\ExportJob;
use App\Shared\Database\MorphMap;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Relance un export.
 *
 * Le §27 autorise cette route « uniquement sans créer de nouvelles colonnes ».
 * Elle n'en crée aucune : elle touche `attemptCount`, `errorMessage` et
 * `sentAt`, rien d'autre. Le statut est **fourni** par l'appelant, le diagramme
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
 *
 * **La relance renvoie réellement, depuis la Phase 6.** Tant qu'aucun export ne
 * partait, remettre les compteurs suffisait ; maintenant qu'une facture est
 * transmise, un bouton « Relancer » qui n'envoie rien mentirait à l'exploitant.
 * Seuls les envois de facture sont remis en file : c'est le seul contenu qu'on
 * sait produire, et rejouer un export dont personne ne génère le fichier le
 * ferait échouer aussitôt.
 *
 * Pour ceux-là, le compteur n'est **pas** incrémenté ici : le renvoi comptera sa
 * propre tentative, et l'avancer deux fois ferait mentir la seule mesure dont
 * l'exploitant dispose pour juger d'une destination.
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
            $resent = $this->resends($locked);

            $locked->update([
                'status' => $status,
                'attempt_count' => $resent ? $locked->attempt_count : $locked->attempt_count + 1,
                'error_message' => null,
            ]);

            if ($resent) {
                // Apres commit : le worker ne doit pas lire une ligne encore
                // verrouillee par cette transaction.
                ProcessExportJob::dispatch($locked->id)->afterCommit();
            }

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

    /** Sait-on refaire cet envoi, ou seulement en remettre les compteurs ? */
    private function resends(ExportJob $job): bool
    {
        return $job->entity_type === MorphMap::INVOICE && $job->entity_id !== null;
    }
}
