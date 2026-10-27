<?php

use App\Modules\Integrations\Jobs\ProcessUploadedImportJob;
use App\Modules\Integrations\Jobs\RunCustomerPullIntegrationJob;

/**
 * `retry_after` de la file `database` doit dépasser le `$timeout` de chaque
 * job : sinon un job encore en cours est repris par un second worker, et un
 * import du portail s'exécute deux fois.
 */
it('laisse finir le plus long job avant de le croire perdu', function (string $job): void {
    $timeout = (new ReflectionClass($job))->getProperty('timeout')->getDefaultValue();

    expect(config('queue.connections.database.retry_after'))->toBeGreaterThan($timeout);
})->with([
    'import de fichier du portail' => ProcessUploadedImportJob::class,
    'relève automatique' => RunCustomerPullIntegrationJob::class,
]);
