<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\Pull;

use App\Modules\Customers\Services\CustomerDefaultAgency;
use App\Modules\Identity\Models\User;
use App\Modules\Integrations\DTOs\ImportRun;
use App\Modules\Integrations\DTOs\ImportSummary;
use App\Modules\Integrations\Exceptions\PermanentIntegrationFailure;
use App\Modules\Integrations\Models\CustomerImportConfiguration;
use App\Modules\Integrations\Services\Import\ImportPipeline;
use App\Modules\Integrations\Services\Import\ImportRunAudit;
use App\Modules\Integrations\Services\Mapping\MappingDirectives;
use App\Modules\Integrations\Services\Sources\ImportSourceKind;
use App\Modules\Integrations\Services\Sources\IntegrationActor;
use App\Modules\Integrations\Services\Sources\RestPullConnections;
use App\Modules\Integrations\Services\Sources\RestPullSettings;
use App\Modules\Integrations\Services\Sources\RestPullSource;
use Illuminate\Validation\ValidationException;

/**
 * Une passe automatique d'une configuration d'import : pull REST ou relève
 * FTP/SFTP, selon `sourceType`. **Une seule classe pour tous les clients** :
 * ce qui les distingue est dans leur configuration.
 *
 * Pull REST : les enregistrements de l'API, puis la chaîne commune. La date
 * du pull n'avance (`last_used_at`) que si tout est passé : une commande
 * refusée sera relue au prochain passage, et l'idempotence ignore celles déjà
 * créées.
 */
final readonly class PullIntegrationRunner
{
    public function __construct(
        private RestPullConnections $connections,
        private RestPullSource $rest,
        private FilePullRunner $files,
        private ImportPipeline $pipeline,
        private IntegrationActor $actors,
        private CustomerDefaultAgency $agencies,
        private ImportRunAudit $audit,
    ) {}

    /**
     * @throws PermanentIntegrationFailure
     */
    public function run(CustomerImportConfiguration $configuration, ?string $jobId = null): ImportSummary
    {
        $configuration->loadMissing('customer');

        if (! $configuration->is_active || $configuration->customer === null) {
            throw new PermanentIntegrationFailure('Cette configuration d’import est désactivée.');
        }

        if (! is_array($configuration->mapping) || $configuration->mapping === []) {
            throw new PermanentIntegrationFailure('Cette configuration ne porte aucune correspondance.');
        }

        return match (ImportSourceKind::of($configuration)) {
            ImportSourceKind::REST_PULL => $this->pullRest($configuration, $jobId),
            ImportSourceKind::FTP, ImportSourceKind::SFTP => $this->files->run($configuration, fn (User $actor): ImportRun => $this->runFor($configuration, $actor), $jobId),
            default => throw new PermanentIntegrationFailure('Cette configuration n’est pas relevée automatiquement (dépôt manuel).'),
        };
    }

    public function runFor(CustomerImportConfiguration $configuration, User $actor): ImportRun
    {
        try {
            $agencyId = $this->agencies->for($configuration->customer);
        } catch (ValidationException) {
            throw new PermanentIntegrationFailure('Aucune agence active ne peut recevoir les commandes de ce client.');
        }

        return ImportRun::make(
            $configuration,
            $configuration->customer->organization_id,
            $actor,
            $agencyId,
            null,
            ImportSourceKind::orderSource($configuration),
            automated: true,
            channel: ImportRun::PULL,
        );
    }

    private function pullRest(CustomerImportConfiguration $configuration, ?string $jobId): ImportSummary
    {
        $api = $this->connections->find($configuration)
            ?? throw new PermanentIntegrationFailure('Aucune connexion REST active n’est configurée pour cet import.');

        $settings = RestPullSettings::from($api->settings);
        $run = $this->runFor($configuration, $this->actors->resolve($configuration->customer->organization_id, $settings->actorUserId, null));
        $startedAt = now();

        $rows = $this->rest->fetch($api, MappingDirectives::recordsPath($configuration->mapping), $api->last_used_at);
        $maxRows = (int) config('customer_integrations.files.max_rows', 5000);

        if (count($rows) > $maxRows) {
            throw new PermanentIntegrationFailure("La réponse porte plus de {$maxRows} enregistrements : réduisez la page ou la fenêtre.");
        }

        $summary = $this->pipeline->run($run, $rows);

        if ($summary->isClean()) {
            $api->forceFill(['last_used_at' => $startedAt])->saveQuietly();
        }

        $this->audit->record($run, $summary, ['jobId' => $jobId], $startedAt);

        return $summary;
    }
}
