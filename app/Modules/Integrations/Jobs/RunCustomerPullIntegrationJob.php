<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Jobs;

use App\Modules\Integrations\DTOs\ImportRun;
use App\Modules\Integrations\Exceptions\PermanentIntegrationFailure;
use App\Modules\Integrations\Exceptions\TransientIntegrationFailure;
use App\Modules\Integrations\Models\CustomerImportConfiguration;
use App\Modules\Integrations\Services\Import\CustomerImportHistory;
use App\Modules\Integrations\Services\Pull\PullIntegrationRunner;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Une passe automatique d'**une** configuration d'import — générique : pull
 * REST ou relève FTP/SFTP, quel que soit le client.
 *
 * - **Jamais deux en même temps** pour une configuration : unique en file
 *   (`ShouldBeUnique`) et exclusif à l'exécution (`WithoutOverlapping`).
 * - **Erreur passagère** (réseau, 429, 5xx, serveur injoignable) : l'exception
 *   remonte, la file relance après `backoff`, au plus `tries` fois.
 * - **Erreur définitive** (401/403, connexion absente, JSON invalide) : le Job
 *   échoue tout de suite — boucler ne corrigerait rien.
 *
 * Le Job ne porte que l'identifiant : la configuration est relue à
 * l'exécution, désactivée entre-temps elle n'est pas traitée.
 */
class RunCustomerPullIntegrationJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries;

    public int $timeout = 600;

    public int $uniqueFor;

    public function __construct(public readonly string $configurationId)
    {
        $this->tries = (int) config('customer_integrations.pull.tries', 3);
        $this->uniqueFor = (int) config('customer_integrations.pull.lock_seconds', 3600);
    }

    public function uniqueId(): string
    {
        return $this->configurationId;
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return array_map('intval', (array) config('customer_integrations.pull.backoff', [60, 300]));
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('customer-import-'.$this->configurationId))
                ->releaseAfter(300)
                ->expireAfter((int) config('customer_integrations.pull.lock_seconds', 3600)),
        ];
    }

    public function handle(PullIntegrationRunner $runner, ?CustomerImportHistory $history = null): void
    {
        $configuration = CustomerImportConfiguration::find($this->configurationId);

        if ($configuration === null || ! $configuration->is_active) {
            return;
        }

        $context = [
            'customerId' => $configuration->customer_id,
            'configurationId' => $configuration->id,
            'jobId' => $this->job?->uuid(),
            'attempt' => $this->attempts(),
        ];

        try {
            $summary = $runner->run($configuration, $this->job?->uuid());
            Log::info('customer-integration.pull.done', [...$context, ...$summary->counts()]);
        } catch (TransientIntegrationFailure $failure) {
            Log::warning('customer-integration.pull.transient', [...$context, 'reason' => $failure->getMessage()]);

            throw $failure;
        } catch (PermanentIntegrationFailure $failure) {
            Log::error('customer-integration.pull.failed', [...$context, 'reason' => $failure->getMessage()]);
            ($history ?? app(CustomerImportHistory::class))->recordFailure($configuration, ImportRun::PULL, $failure->getMessage(), null, ['jobId' => $context['jobId']]);
            $this->fail($failure);
        }
    }

    /**
     * Toutes les tentatives épuisées sur une erreur passagère : la relève
     * entre dans l'historique comme échouée. Une erreur définitive y est déjà
     * (voir `handle`).
     */
    public function failed(?Throwable $exception): void
    {
        $configuration = CustomerImportConfiguration::find($this->configurationId);

        if ($configuration === null || ! $exception instanceof TransientIntegrationFailure) {
            return;
        }

        app(CustomerImportHistory::class)->recordFailure(
            $configuration,
            ImportRun::PULL,
            "Relève abandonnée après {$this->tries} tentatives : {$exception->getMessage()}",
            null,
            ['jobId' => $this->job?->uuid()],
        );
    }
}
