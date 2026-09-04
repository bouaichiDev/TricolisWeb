<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Addresses\Models\Address;
use App\Modules\Addresses\Models\EntityAddress;
use App\Modules\Planning\Services\GeocodingService;
use Illuminate\Console\Command;

/**
 * Géocode les adresses déjà en base qui n'ont pas de point.
 *
 * Le déclenchement automatique ne vaut que pour ce qui est créé ou modifié
 * ensuite : tout ce qui existait avant reste sans coordonnées, donc absent de
 * la carte. Cette commande rattrape ce stock.
 *
 * **Elle appelle le service directement, sans passer par la file.** C'est un
 * travail d'administration qu'on lance en le regardant : voir passer les
 * refus, et pouvoir arrêter, vaut mieux qu'un millier de Jobs anonymes.
 *
 * `--limit` borne le lot. Le service de géocodage a un quota ; vider trois
 * mille adresses d'un coup le dépasserait, et les échecs qui suivent ne se
 * distingueraient pas d'une adresse introuvable.
 */
class GeocodeAddresses extends Command
{
    protected $signature = 'tricolis:geocode-addresses
        {--organization= : N’examiner qu’une organisation}
        {--limit=50 : Nombre maximum d’adresses traitées}
        {--dry-run : Compter sans appeler le service}';

    protected $description = 'Donne leurs coordonnées aux adresses qui n’en ont pas';

    public function handle(GeocodingService $geocoding): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $organization = $this->option('organization');

        // L'organisation vient de la liaison : `addresses` n'en porte pas, ce
        // qui est voulu — une adresse est partagee, ses liaisons ne le sont pas.
        $links = EntityAddress::query()
            ->when(is_string($organization), fn ($query) => $query->where('organization_id', $organization))
            ->whereIn('address_id', Address::query()
                ->where(fn ($address) => $address->whereNull('latitude')->orWhereNull('longitude'))
                ->select('id'))
            ->orderBy('address_id')
            ->get()
            ->unique('address_id')
            ->take($limit);

        if ($links->isEmpty()) {
            $this->info('Aucune adresse à géocoder.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("{$links->count()} adresse(s) à géocoder.");

            return self::SUCCESS;
        }

        $located = 0;
        $failed = [];

        foreach ($links as $link) {
            $address = Address::find($link->address_id);

            if ($address === null) {
                continue;
            }

            if ($geocoding->locate($address, $link->organization_id)) {
                $located++;

                continue;
            }

            $failed[] = $geocoding->describe($address);
        }

        $this->info("{$located} adresse(s) située(s).");

        foreach ($failed as $description) {
            $this->warn('Non trouvée : '.($description === '' ? '(adresse vide)' : $description));
        }

        return self::SUCCESS;
    }
}
