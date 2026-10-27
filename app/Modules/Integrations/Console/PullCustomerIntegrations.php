<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Console;

use App\Modules\Integrations\Jobs\RunCustomerPullIntegrationJob;
use App\Modules\Integrations\Models\CustomerImportConfiguration;
use App\Modules\Integrations\Services\Sources\ImportSourceKind;
use App\Modules\Integrations\Services\Sources\InboundFileConnections;
use App\Modules\Integrations\Services\Sources\RestPullConnections;
use Illuminate\Console\Command;

/**
 * Le runner générique des imports automatiques, lancé chaque heure.
 *
 * Il ne traite rien lui-même : il **sélectionne** les configurations actives
 * dont le canal est automatique (pull REST, FTP, SFTP) et dont la connexion
 * existe, puis met une passe en file pour chacune. Aucun client n'est nommé
 * ici — ni dans le scheduler.
 */
class PullCustomerIntegrations extends Command
{
    protected $signature = 'customer-integrations:pull {--configuration= : Une seule configuration, par identifiant}';

    protected $description = 'Met en file une passe de chaque import client automatique (pull REST, FTP/SFTP) actif.';

    public function handle(RestPullConnections $rest, InboundFileConnections $files): int
    {
        $configurations = CustomerImportConfiguration::with('customer')
            ->where('is_active', true)
            ->when($this->option('configuration'), fn ($query, $id) => $query->whereKey($id))
            ->orderBy('id')
            ->get();

        $queued = 0;

        foreach ($configurations as $configuration) {
            $ready = match (ImportSourceKind::of($configuration)) {
                ImportSourceKind::REST_PULL => $rest->find($configuration) !== null,
                ImportSourceKind::FTP, ImportSourceKind::SFTP => $files->isConfigured($configuration),
                default => false,
            };

            if (! $ready) {
                continue;
            }

            RunCustomerPullIntegrationJob::dispatch($configuration->id);
            $queued++;
        }

        $this->info("{$queued} import(s) automatique(s) mis en file.");

        return self::SUCCESS;
    }
}
