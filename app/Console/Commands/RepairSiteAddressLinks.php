<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Addresses\Models\EntityAddress;
use App\Modules\Customers\Models\CustomerSite;
use App\Shared\Database\MorphMap;
use Illuminate\Console\Command;

/**
 * Rattache l'adresse de chaque site client au site, et non au client.
 *
 * Le frontend créait l'adresse d'un site en la rattachant au **client** : elle
 * apparaissait alors dans les adresses du client, à côté de ses adresses de
 * livraison et de facturation, alors qu'elle appartient au site. La création a
 * été corrigée ; les sites créés auparavant portent toujours la mauvaise
 * liaison, que cette commande répare.
 *
 * La règle est sûre parce qu'elle est vérifiable : une adresse désignée par
 * `customer_sites.address_id` **est** l'adresse d'un site. Une liaison vers le
 * client portant cette même adresse ne peut donc être que l'artefact du défaut.
 *
 * Aucune adresse n'est supprimée : seule la liaison fautive l'est, après que la
 * bonne a été créée.
 */
class RepairSiteAddressLinks extends Command
{
    protected $signature = 'tricolis:repair-site-address-links {--dry-run : Montrer sans modifier}';

    protected $description = 'Rattache l’adresse de chaque site client au site plutôt qu’au client';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $linked = 0;
        $unlinked = 0;

        CustomerSite::query()->with('customer')->each(function (CustomerSite $site) use ($dryRun, &$linked, &$unlinked): void {
            $organizationId = $site->customer?->organization_id;

            if ($organizationId === null || $site->address_id === null) {
                return;
            }

            $linked += $this->ensureSiteLink($site, $organizationId, $dryRun);
            $unlinked += $this->dropCustomerLink($site, $organizationId, $dryRun);
        });

        $this->info(($dryRun ? '[simulation] ' : '')."Liaisons vers le site créées : {$linked}.");
        $this->info(($dryRun ? '[simulation] ' : '')."Liaisons vers le client retirées : {$unlinked}.");

        return self::SUCCESS;
    }

    /** La bonne liaison est créée en premier : l'adresse n'est jamais orpheline. */
    private function ensureSiteLink(CustomerSite $site, string $organizationId, bool $dryRun): int
    {
        $exists = EntityAddress::where('address_id', $site->address_id)
            ->where('entity_type', MorphMap::CUSTOMER_SITE)
            ->where('entity_id', $site->id)
            ->exists();

        if ($exists) {
            return 0;
        }

        $this->line("  {$site->code} : rattachement au site");

        if (! $dryRun) {
            EntityAddress::create([
                'organization_id' => $organizationId,
                'address_id' => $site->address_id,
                'entity_type' => MorphMap::CUSTOMER_SITE,
                'entity_id' => $site->id,
                'address_type' => 'delivery',
                'is_default' => (bool) $site->is_default,
            ]);
        }

        return 1;
    }

    private function dropCustomerLink(CustomerSite $site, string $organizationId, bool $dryRun): int
    {
        $stray = EntityAddress::where('address_id', $site->address_id)
            ->where('organization_id', $organizationId)
            ->where('entity_type', MorphMap::CUSTOMER)
            ->where('entity_id', $site->customer_id)
            ->get();

        if ($stray->isEmpty()) {
            return 0;
        }

        $this->line("  {$site->code} : retrait de la liaison vers le client");

        if (! $dryRun) {
            EntityAddress::whereKey($stray->pluck('id'))->delete();
        }

        return $stray->count();
    }
}
