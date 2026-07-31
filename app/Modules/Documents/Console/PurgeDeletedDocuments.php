<?php

declare(strict_types=1);

namespace App\Modules\Documents\Console;

use App\Modules\Documents\Models\Document;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Purge définitivement les documents supprimés au-delà de la période de rétention.
 */
class PurgeDeletedDocuments extends Command
{
    protected $signature = 'documents:purge {--days= : Période de rétention en jours}';

    protected $description = 'Supprime définitivement les documents et fichiers au-delà de la rétention.';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('tricolis.document_retention_days'));
        $threshold = now()->subDays($days);
        $purged = 0;

        Document::onlyTrashed()
            ->where('deleted_at', '<=', $threshold)
            ->chunkById(100, function ($documents) use (&$purged): void {
                foreach ($documents as $document) {
                    Storage::disk('local')->delete($document->storage_path);
                    $document->links()->delete();
                    $document->forceDelete();
                    $purged++;
                }
            });

        $this->info("$purged document(s) purgé(s) après $days jour(s) de rétention.");

        return self::SUCCESS;
    }
}
