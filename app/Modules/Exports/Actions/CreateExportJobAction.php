<?php

declare(strict_types=1);

namespace App\Modules\Exports\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Exports\DTOs\CreateExportJobData;
use App\Modules\Exports\Models\ExportJob;
use App\Modules\Integrations\Services\IntegrationScopeGuard;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Enregistre une demande d'export.
 *
 * Le client est **déduit** de la configuration, jamais accepté en entrée : le
 * §24 impose que les deux concordent, et déduire est plus sûr que comparer.
 *
 * La configuration doit être **active** — déclencher un export sur une
 * configuration désactivée produirait un job que personne ne traitera.
 *
 * `attemptCount` démarre à zéro. **Aucun traitement n'est déclenché** : voir
 * `phase-8-analysis.md` §11 — les règles de contenu des exports ne sont
 * définies nulle part, et le §30 interdit de produire un faux export métier.
 */
final readonly class CreateExportJobAction
{
    public function __construct(
        private IntegrationScopeGuard $guard,
        private WriteAuditLog $audit,
    ) {}

    public function execute(CreateExportJobData $data, AuditContext $context): ExportJob
    {
        $configuration = $this->guard->activeExportConfiguration($data->configurationId, $context->organizationId);

        return DB::transaction(function () use ($data, $configuration, $context): ExportJob {
            $job = ExportJob::create($data->toAttributes($configuration->customer_id))->refresh();

            $this->audit->execute(
                $context->organizationId,
                $context->user,
                'export_job.created',
                $job,
                null,
                $job->only(['customer_id', 'configuration_id', 'entity_type', 'entity_id', 'status']),
                null,
                $context->ipAddress,
            );

            return $job;
        });
    }
}
