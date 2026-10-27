<?php

declare(strict_types=1);

namespace App\Modules\Exports\Services\Content;

use App\Modules\Exports\Models\CustomerExportConfiguration;
use App\Modules\Exports\Models\ExportJob;
use RuntimeException;

/**
 * Construit le contenu d'un export pour un type d'entité.
 *
 * Chaque implémentation **revérifie** son entité (existence, état, client) :
 * un `ExportJob` fabriqué à la main ne fait pas partir ce qui ne doit pas.
 */
interface ExportContentBuilder
{
    /**
     * @throws RuntimeException entité absente, non éligible ou d'un autre client
     */
    public function build(ExportJob $job, CustomerExportConfiguration $configuration): ExportContent;
}
