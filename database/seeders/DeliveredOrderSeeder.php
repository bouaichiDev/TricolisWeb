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
use App\Modules\Providers\Models\Provider;
use Carbon\CarbonImmutable;
use Database\Seeders\Support\DeliveredOrderFactory;
use Database\Seeders\Support\DeliveredTourBuilder;
use Database\Seeders\Support\SeededCustomer;
use Database\Seeders\Support\SwissCatalogue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Un mois de commandes déjà livrées, prêtes à facturer.
 *
 * Le semis suisse remplit la file de planification ; celui-ci remplit ce qui
 * vient après. Sans lui, les écrans de facturation s'ouvrent sur une liste vide
 * et ne se testent pas : le sélecteur ne retient que les prestations
 * **terminées** et non encore facturées.
 *
 * **Chaque livraison passe par une tournée, et la plupart par un fournisseur.**
 * C'est la condition du §17 : une prestation n'est réglable qu'à celui dont
 * l'affectation est active. Un jour sur trois roule en propre — sans
 * fournisseur — pour que la différence se voie : ces prestations restent
 * facturables au client et ne se règlent à personne.
 */
class DeliveredOrderSeeder extends Seeder
{
    private const int DAYS = 12;

    private const int ORDERS_PER_DAY = 5;

    /** Deux partenaires : un décompte se lit mieux quand il en existe un autre. */
    private const array PROVIDERS = [
        'partner-leman' => 'Transports Léman SA',
        'partner-alpes' => 'Alpes Express Sàrl',
    ];

    public function run(): void
    {
        // `local` seulement : soixante commandes livrees par organisation
        // fausseraient toute assertion qui compte ou pagine.
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
            $this->command?->warn("Livraisons ignorées pour {$organization->code} : dépôt ou utilisateur manquant.");

            return;
        }

        // Rejouable : le prefixe de reference distingue ces commandes de celles
        // du semis de planification, qui vivent dans la meme agence.
        if (Order::where('organization_id', $organization->id)
            ->where('customer_reference', 'like', 'LIV-%')->exists()) {
            return;
        }

        $catalogue = app(SwissCatalogue::class);

        $factory = new DeliveredOrderFactory(
            $organization->id,
            $agency->id,
            $depot->id,
            $depotAddressId,
            $catalogue->loadingServiceId($organization),
            $catalogue->deliveryServiceId($organization),
            $userId,
        );

        $this->fill(
            $organization,
            $factory,
            new DeliveredTourBuilder($organization->id, $agency->id, $depot->id, $depotAddressId),
            $catalogue->customers($organization),
            $this->providers($organization),
        );
    }

    /**
     * @param  list<SeededCustomer>  $customers
     * @param  list<string>  $providerIds
     */
    private function fill(
        Organization $organization,
        DeliveredOrderFactory $factory,
        DeliveredTourBuilder $tours,
        array $customers,
        array $providerIds,
    ): void {
        $numbers = app(GenerateOrderNumber::class);
        // Le mois ecoule : une facture porte sur ce qui a ete fait, pas sur ce
        // qui reste a faire.
        $start = CarbonImmutable::today()->subDays(self::DAYS);
        $index = 0;

        for ($day = 0; $day < self::DAYS; $day++) {
            $date = $start->addDays($day);

            // Un jour sur trois roule en propre : ces livraisons se facturent
            // au client sans se regler a personne.
            $providerId = $day % 3 === 2 ? null : $providerIds[$day % count($providerIds)];

            DB::transaction(function () use (
                $factory, $tours, $numbers, $organization, $customers, $date, $providerId, &$index
            ): void {
                $tour = $tours->open($date, $providerId);

                for ($position = 0; $position < self::ORDERS_PER_DAY; $position++) {
                    $customer = $customers[$index % count($customers)];

                    $created = $factory->create(
                        $numbers->execute($organization->id, $date->year, $customer->code),
                        $date,
                        $index,
                        $customer,
                    );

                    $tours->attach($tour, $created['loading'], $created['delivery']);
                    $index++;
                }

                $tours->close($tour);
            });

            $this->command?->getOutput()?->write('.');
        }

        $this->command?->newLine();
        $this->command?->info("{$index} commandes livrées créées pour {$organization->code}.");
    }

    /**
     * Les partenaires à qui l'on doit quelque chose.
     *
     * Créés ici et non dans le semis de flotte : celui-ci ne sert que
     * l'organisation de développement, et un décompte sans fournisseur n'a
     * personne à payer.
     *
     * @return list<string>
     */
    private function providers(Organization $organization): array
    {
        $ids = [];

        foreach (self::PROVIDERS as $code => $name) {
            $ids[] = Provider::firstOrCreate(
                ['organization_id' => $organization->id, 'code' => $code],
                ['name' => $name, 'status' => 'active'],
            )->id;
        }

        return $ids;
    }
}
