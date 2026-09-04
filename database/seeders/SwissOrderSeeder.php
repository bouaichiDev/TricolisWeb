<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Addresses\Models\EntityAddress;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\Depot;
use App\Modules\Orders\Actions\GenerateOrderNumber;
use App\Modules\Orders\Models\Order;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationUser;
use Carbon\CarbonImmutable;
use Database\Seeders\Support\SeededCustomer;
use Database\Seeders\Support\SwissCatalogue;
use Database\Seeders\Support\SwissOrderFactory;
use Database\Seeders\Support\SwissServiceMix;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Un mois de commandes suisses, prêtes à planifier.
 *
 * Trente commandes par jour sur trente jours : de quoi voir une file de
 * planification se remplir, une carte se couvrir, et des tournées se comparer.
 * Chaque commande porte ses articles, ses colis, et **de une à quatre
 * prestations** — {@see SwissServiceMix} fait tourner les combinaisons, pour que
 * le jeu d'essai ne se réduise pas à une forme unique.
 *
 * Elles se répartissent sur **cinq clients**, chacun avec ses trois adresses.
 * Un client par localité donnait trente clients d'une commande chacun : ni la
 * facturation, ni le stock, ni un import client ne s'y éprouvaient.
 *
 * **Les coordonnées viennent du carnet, pas du service de géocodage.** Neuf
 * cents appels épuiseraient le quota, et une adresse inventée ne se géocode pas.
 * Le carnet porte des points réels, ce qui rend la carte et les distances
 * exploitables dès le semis.
 *
 * Le semis est **rejouable** : il ne fait rien si l'agence suisse porte déjà des
 * commandes. Sans cette garde, un second passage doublerait le mois.
 */
class SwissOrderSeeder extends Seeder
{
    private const int DAYS = 30;

    private const int ORDERS_PER_DAY = 30;

    public function run(): void
    {
        // `local` seulement, et non `testing` : neuf cents commandes par
        // organisation alourdiraient chaque test d'une demi-minute et
        // fausseraient toute assertion qui compte, pagine ou cherche.
        if (! app()->environment('local')) {
            return;
        }

        foreach (Organization::cursor() as $organization) {
            $this->seedFor($organization);
        }
    }

    private function seedFor(Organization $organization): void
    {
        $agency = Agency::where('organization_id', $organization->id)
            ->where('code', SwissDepotSeeder::AGENCY_CODE)->first();

        $depot = $agency === null ? null : Depot::where('agency_id', $agency->id)
            ->where('code', SwissDepotSeeder::DEPOT_CODE)->first();

        $depotAddressId = $depot === null ? null : EntityAddress::where('entity_type', 'depot')
            ->where('entity_id', $depot->id)->value('address_id');

        $userId = OrganizationUser::where('organization_id', $organization->id)->value('user_id');

        if ($agency === null || $depot === null || $depotAddressId === null || $userId === null) {
            $this->command?->warn("Semis suisse ignoré pour {$organization->code} : dépôt ou utilisateur manquant.");

            return;
        }

        if (Order::where('organization_id', $organization->id)->where('agency_id', $agency->id)->exists()) {
            return;
        }

        $catalogue = app(SwissCatalogue::class);

        // Le chargement d'abord : il declare son code dans les reglages, et
        // c'est lui que le pool reconnait pour regrouper au depot.
        $catalogue->loadingServiceId($organization);

        $factory = new SwissOrderFactory(
            $organization->id,
            $agency->id,
            $depot->id,
            new SwissServiceMix(
                $catalogue->services($organization),
                $catalogue->serviceMinutes(),
                $depotAddressId,
            ),
            $userId,
        );

        $this->fill($organization, $factory, $catalogue->customers($organization));
    }

    /**
     * @param  list<SeededCustomer>  $customers
     */
    private function fill(Organization $organization, SwissOrderFactory $factory, array $customers): void
    {
        $numbers = app(GenerateOrderNumber::class);
        $start = CarbonImmutable::today();
        $index = 0;

        for ($day = 0; $day < self::DAYS; $day++) {
            $date = $start->addDays($day);

            // Une transaction par jour : le numero de commande exige un verrou,
            // et une seule transaction pour neuf cents commandes le tiendrait
            // trop longtemps.
            DB::transaction(function () use ($factory, $numbers, $organization, $customers, $date, &$index): void {
                for ($position = 0; $position < self::ORDERS_PER_DAY; $position++) {
                    $customer = $customers[$index % count($customers)];

                    $factory->create(
                        $numbers->execute($organization->id, $date->year, $customer->code),
                        $date,
                        $index,
                        $customer,
                    );

                    $index++;
                }
            });

            $this->command?->getOutput()?->write('.');
        }

        $this->command?->newLine();
        $this->command?->info("{$index} commandes suisses créées pour {$organization->code}.");
    }
}
