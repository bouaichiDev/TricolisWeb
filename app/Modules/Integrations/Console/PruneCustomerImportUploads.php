<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Console;

use App\Modules\Integrations\Services\Uploads\ImportUploadStore;
use Illuminate\Console\Command;

/**
 * Supprime les fichiers d'import prévisualisés puis jamais confirmés.
 *
 * Un dépôt confirmé est supprimé aussitôt ; celui qu'on abandonne resterait
 * sur le disque sans ce ménage, avec des données client dedans.
 */
class PruneCustomerImportUploads extends Command
{
    protected $signature = 'customer-integrations:prune-uploads';

    protected $description = 'Supprime les fichiers d’import client prévisualisés dont le jeton a expiré.';

    public function handle(ImportUploadStore $store): int
    {
        $this->info($store->prune().' fichier(s) d’import expiré(s) supprimé(s).');

        return self::SUCCESS;
    }
}
