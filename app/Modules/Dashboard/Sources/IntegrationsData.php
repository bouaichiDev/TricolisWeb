<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Sources;

use App\Modules\Dashboard\Services\DashboardContext;
use App\Modules\Dashboard\Services\DashboardDataSource;
use App\Modules\Dashboard\Services\DashboardPayload;
use App\Modules\Exports\Models\CustomerExportConfiguration;
use App\Modules\Exports\Models\ExportJob;
use App\Modules\Integrations\Models\OrganizationApiConfiguration;
use App\Shared\Database\MorphMap;
use Illuminate\Database\Eloquent\Builder;

/**
 * Envois de fichiers et accès aux API.
 *
 * Ces widgets **comptent, ils ne montrent pas**. Une configuration d'export
 * porte un hôte, un identifiant, un mot de passe chiffré et un répertoire de
 * dépôt ; une configuration d'API porte des identifiants chiffrés. Le compteur
 * n'en rend que le nombre, et la liste des envois récents ne rend que le nom du
 * fichier et son état — jamais `storage_path`, qui dirait où le lire, jamais
 * `error_message`, qui cite parfois l'hôte et le chemin distants.
 */
final readonly class IntegrationsData implements DashboardDataSource
{
    private const string FAILED = 'failed';

    private const string PENDING = 'pending';

    private const string SENT = 'sent';

    /**
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    public function resolve(array $keys, DashboardContext $context): array
    {
        $data = [];

        foreach ($keys as $key) {
            $data[$key] = $this->resolveOne($key, $context);
        }

        return $data;
    }

    private function resolveOne(string $key, DashboardContext $context): mixed
    {
        return match ($key) {
            'export_jobs_failed' => DashboardPayload::alert(
                $this->jobs($context)->where('status', self::FAILED)->count()
            ),
            'export_jobs_pending' => DashboardPayload::kpi(
                $this->jobs($context)->where('status', self::PENDING)->count()
            ),
            'exports_sent_today' => DashboardPayload::kpi(
                $this->jobs($context)
                    ->where('status', self::SENT)
                    ->whereBetween('sent_at', $context->dayBounds())
                    ->count()
            ),
            'recent_export_jobs' => DashboardPayload::list($this->recentJobs($context)),

            'active_api_configurations' => DashboardPayload::kpi(
                OrganizationApiConfiguration::query()
                    ->where('organization_id', $context->organizationId)
                    ->where('is_active', true)
                    ->count()
            ),

            // Les configurations d'export appartiennent au client, pas à
            // l'organisation : le scope du modèle porte déjà la jointure.
            'active_export_configurations' => DashboardPayload::kpi(
                CustomerExportConfiguration::query()
                    ->inOrganization($context->organizationId)
                    ->where('is_active', true)
                    ->count()
            ),

            default => null,
        };
    }

    /**
     * @return Builder<ExportJob>
     */
    private function jobs(DashboardContext $context): Builder
    {
        return ExportJob::query()->inOrganization($context->organizationId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentJobs(DashboardContext $context): array
    {
        return $this->jobs($context)
            ->orderByDesc('generated_at')
            ->limit(6)
            ->get(['id', 'file_name', 'entity_type', 'status', 'generated_at'])
            ->map(static fn (ExportJob $job): array => [
                'id' => $job->getKey(),
                'title' => $job->getAttribute('file_name'),
                'subtitle' => $job->getAttribute('entity_type'),
                'status' => $job->getAttribute('status'),
                'statusSource' => MorphMap::EXPORT_JOB,
                'date' => $job->getAttribute('generated_at')?->toIso8601String(),
                'route' => '/integrations/export-jobs/'.$job->getKey(),
            ])
            ->all();
    }
}
