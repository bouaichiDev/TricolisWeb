<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Organizations\Actions\SyncOrganizationMenu;
use App\Modules\Organizations\Models\Organization;
use Illuminate\Console\Command;

/**
 * Donne à chaque organisation les entrées de menu qui lui manquent.
 *
 * **À exécuter après chaque phase qui enrichit le catalogue.** Une organisation
 * créée avant l'ajout n'a pas de ligne pour la nouvelle entrée : la commande la
 * lui crée, visible par défaut.
 *
 * Ne touche jamais à une ligne existante : ce qu'une organisation a choisi de
 * masquer le reste. Rejouable sans effet de bord.
 */
class SyncOrganizationMenus extends Command
{
    protected $signature = 'tricolis:sync-organization-menus
                            {--organization= : Limiter à une organisation (identifiant ou code)}
                            {--reposition : Remettre les entrées existantes au rang du catalogue}';

    protected $description = 'Crée les entrées de menu manquantes de chaque organisation';

    public function handle(SyncOrganizationMenu $sync): int
    {
        $query = Organization::query();

        if ($target = $this->option('organization')) {
            $query->where(fn ($builder) => $builder->where('id', $target)->orWhere('code', $target));
        }

        $organizations = $query->get();

        if ($organizations->isEmpty()) {
            $this->error('Aucune organisation ne correspond.');

            return self::FAILURE;
        }

        $total = 0;

        foreach ($organizations as $organization) {
            $created = $sync->execute($organization->id, (bool) $this->option('reposition'));
            $total += $created;

            $this->line($created > 0
                ? "  {$organization->code} : {$created} entrée(s) ajoutée(s)"
                : "  {$organization->code} : déjà à jour");
        }

        $this->info("{$total} entrée(s) ajoutée(s) sur {$organizations->count()} organisation(s).");

        return self::SUCCESS;
    }
}
