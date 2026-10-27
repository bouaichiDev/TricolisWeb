<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Actions;

use App\Modules\CustomerPortal\Exceptions\CustomerImportRefused;
use App\Modules\CustomerPortal\Services\CurrentCustomerContext;
use App\Modules\CustomerPortal\Services\CustomerPortalAudit;
use App\Modules\CustomerPortal\Services\Integrations\CustomerImportRuns;
use App\Modules\Integrations\DTOs\ImportRun;
use App\Modules\Integrations\Jobs\ProcessUploadedImportJob;
use App\Modules\Integrations\Models\CustomerImportConfiguration;
use App\Modules\Integrations\Services\Import\CustomerImportHistory;
use App\Modules\Integrations\Services\Import\ImportPipeline;
use App\Modules\Integrations\Services\Import\ImportRunAudit;
use App\Modules\Integrations\Services\Import\OrderGroups;
use App\Modules\Integrations\Services\ImportSourceReader;
use App\Modules\Integrations\Services\Mapping\MappingDirectives;
use App\Modules\Integrations\Services\Uploads\ImportUploadStore;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Confirmer un import prévisualisé.
 *
 * Tout est **revérifié** : le jeton (client, configuration, utilisateur), la
 * configuration (active, en dépôt manuel), le fichier relu, la
 * correspondance réappliquée, chaque commande revalidée par l'Action
 * d'import. Les commandes valides sont créées ; les autres sont rendues avec
 * leurs erreurs (§31). Le jeton est consommé : le même fichier ne se
 * confirme pas deux fois — et s'il l'était, l'idempotence l'ignorerait.
 *
 * Au-delà de `sync_order_limit` commandes, l'import part en file (§29) : la
 * réponse rend le jeton, qui sert à lire le résumé une fois le travail fini.
 */
final readonly class ExecuteCustomerImport
{
    public function __construct(
        private CurrentCustomerContext $context,
        private ImportUploadStore $uploads,
        private ImportSourceReader $reader,
        private OrderGroups $groups,
        private ImportPipeline $pipeline,
        private CustomerImportRuns $runs,
        private ImportRunAudit $runAudit,
        private CustomerPortalAudit $audit,
        private CustomerImportHistory $history,
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @throws CustomerImportRefused|ModelNotFoundException
     */
    public function execute(CustomerImportConfiguration $configuration, string $token, Request $request): array
    {
        $entry = $this->uploads->find($token, $this->context->customerId(), $configuration->id);

        if ($entry === null || $entry['userId'] !== $this->context->user()->id) {
            throw (new ModelNotFoundException)->setModel(CustomerImportConfiguration::class);
        }

        $refusal = CustomerImportRuns::refusal($configuration);

        if ($refusal !== null) {
            throw new CustomerImportRefused($refusal);
        }

        try {
            $rows = $this->reader->read($this->uploads->contents($entry), (string) $configuration->file_format, MappingDirectives::recordsPath($configuration->mapping));
        } catch (RuntimeException $exception) {
            $this->history->recordFailure($configuration, ImportRun::PORTAL, $exception->getMessage(), $this->context->user()->id, ['file' => $entry['fileName']]);

            throw new CustomerImportRefused($exception->getMessage());
        }

        $orderCount = count($this->groups->split($configuration->mapping ?? [], $rows));

        if ($orderCount > (int) config('customer_integrations.uploads.sync_order_limit', 200)) {
            $this->uploads->putResult($token, $this->context->customerId(), ['status' => 'queued', 'fileName' => $entry['fileName']]);
            ProcessUploadedImportJob::dispatch($token, $configuration->id, $this->context->customerId(), $this->context->user()->id);

            return ['status' => 'queued', 'token' => $token, 'fileName' => $entry['fileName'], 'orderCount' => $orderCount];
        }

        $startedAt = now();
        $run = $this->runs->for($configuration, $this->context->user(), $request);
        $summary = $this->pipeline->run($run, $rows);
        $this->uploads->forget($token, $entry);

        $this->runAudit->record($run, $summary, ['file' => $entry['fileName']], $startedAt);
        $this->audit->record($this->context->customer(), $this->context->user(), 'integration_import_executed', $configuration, null, [
            'configurationId' => $configuration->id,
            ...$summary->counts(),
        ], $request->ip());

        return ['status' => 'completed', 'token' => $token, 'fileName' => $entry['fileName'], ...$summary->toArray()];
    }
}
