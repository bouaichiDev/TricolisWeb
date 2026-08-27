<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Models\Service;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Planning\Services\LoadingServices;

/**
 * Ce dont un mois de commandes a besoin avant d'exister : des clients et deux
 * services.
 *
 * Séparé du semis parce que ce sont deux questions distinctes — quoi créer, et
 * sur quoi l'accrocher. La seconde se résout une fois, la première neuf cents.
 */
final readonly class SwissCatalogue
{
    public function __construct(private LoadingServices $loading) {}

    /**
     * Un client par localité.
     *
     * Trente adresses réparties sur trente villes donnent une carte lisible, là
     * où un client unique en ferait un tas.
     *
     * @return list<string>
     */
    public function customers(Organization $organization): array
    {
        $ids = [];

        for ($index = 0; $index < SwissAddressBook::localityCount(); $index++) {
            $city = SwissAddressBook::at($index)['city'];

            $ids[] = Customer::firstOrCreate(
                ['organization_id' => $organization->id, 'code' => sprintf('CH-%02d', $index + 1)],
                [
                    'name' => sprintf('Client %s', $city),
                    'email' => sprintf('contact%d@example.ch', $index + 1),
                    'status' => 'active',
                ],
            )->id;
        }

        return $ids;
    }

    /**
     * Le service de chargement de l'organisation, créé si elle n'en a pas.
     *
     * La reconnaissance passe par les codes réglés — jamais par une constante
     * écrite ici : c'est `LoadingServices` qui fait autorité, et le semis s'y
     * plie. Quand il en crée un, il déclare aussi son code dans les réglages,
     * sans quoi le regroupement au dépôt ne le reconnaîtrait pas.
     */
    public function loadingServiceId(Organization $organization): string
    {
        $existing = $this->loading->serviceIds($organization);

        if ($existing !== []) {
            return $existing[0];
        }

        $service = $this->service($organization, 'LOAD', 'Chargement');

        $settings = $organization->settings ?? [];
        data_set($settings, LoadingServices::SETTING_PATH, ['LOAD']);
        $organization->forceFill(['settings' => $settings])->save();

        return $service;
    }

    /** La livraison : le premier service qui n'est pas un chargement. */
    public function deliveryServiceId(Organization $organization): string
    {
        $loadingIds = $this->loading->serviceIds($organization);

        $delivery = Service::where('organization_id', $organization->id)
            ->whereNotIn('id', $loadingIds === [] ? ['-'] : $loadingIds)
            ->orderBy('code')
            ->value('id');

        return $delivery ?? $this->service($organization, 'DELIVERY', 'Livraison');
    }

    private function service(Organization $organization, string $code, string $name): string
    {
        return Service::firstOrCreate(
            ['organization_id' => $organization->id, 'code' => $code],
            [
                'name' => $name,
                'unit' => 'commande',
                'default_duration_minutes' => 30,
                'billable_to_customer' => true,
                'payable_to_provider' => true,
                'requires_address' => true,
                'requires_contact' => false,
                'status' => 'active',
            ],
        )->id;
    }
}
