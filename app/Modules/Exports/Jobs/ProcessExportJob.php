<?php

declare(strict_types=1);

namespace App\Modules\Exports\Jobs;

use App\Modules\Exports\Models\ExportJob;
use App\Modules\Exports\Services\ExportDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Génère, stocke et transmet un export.
 *
 * En file, et non pendant la requête : le §161 l'exige, et le §26 interdit de
 * tenir une transaction ouverte pendant un appel réseau. La clôture d'une
 * facture rend la main tout de suite ; l'envoi suit.
 *
 * Le Job ne porte que l'identifiant — recharger à l'exécution garantit qu'il
 * agit sur l'état courant, et non sur une copie sérialisée à la mise en file.
 */
class ProcessExportJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $exportJobId) {}

    public function handle(ExportDispatcher $dispatcher): void
    {
        $job = ExportJob::find($this->exportJobId);

        if ($job === null) {
            return;
        }

        $dispatcher->process($job);
    }
}
