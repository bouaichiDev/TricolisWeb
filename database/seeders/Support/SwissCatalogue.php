<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

use App\Modules\Orders\Models\Service;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Planning\Services\LoadingServices;

/**
 * Ce dont un mois de commandes a besoin avant d'exister : des clients et des
 * services.
 *
 * Séparé du semis parce que ce sont deux questions distinctes — quoi créer, et
 * sur quoi l'accrocher. La seconde se résout une fois, la première neuf cents.
 *
 * **Quatre services, et non deux.** Une commande qui porte toujours les mêmes
 * deux prestations ne prouve rien : ni qu'un arrêt cumule correctement ses
 * durées, ni qu'une facture distingue ce qu'elle facture. Le montage et le
 * déballage donnent au semis de quoi varier.
 */
final readonly class SwissCatalogue
{
    /**
     * Les services du semis : code → nom, unité, durée par défaut.
     *
     * @var array<string, array{0: string, 1: string, 2: int}>
     */
    private const array SERVICES = [
        'LOAD' => ['Chargement', 'commande', 20],
        'DELIVERY' => ['Livraison', 'commande', 30],
        'MONTAGE' => ['Montage', 'colis', 45],
        'DEBALLAGE' => ['Déballage', 'colis', 20],
    ];

    public function __construct(
        private LoadingServices $loading,
        private SwissCustomerBook $book,
    ) {}

    /**
     * Cinq clients, chacun avec ses trois adresses et leurs contacts.
     *
     * @return list<SeededCustomer>
     */
    public function customers(Organization $organization): array
    {
        return $this->book->forOrganization($organization);
    }

    /**
     * Les services du semis, par code.
     *
     * @return array<string, string>
     */
    public function services(Organization $organization): array
    {
        $ids = [];

        foreach (self::SERVICES as $code => [$name, $unit, $minutes]) {
            $ids[$code] = $this->service($organization, $code, $name, $unit, $minutes);
        }

        return $ids;
    }

    /**
     * Les durées par défaut, par code — ce qu'un service met sur place.
     *
     * @return array<string, int>
     */
    public function serviceMinutes(): array
    {
        return array_map(static fn (array $service): int => $service[2], self::SERVICES);
    }

    /**
     * Le service de chargement de l'organisation, créé si elle n'en a pas.
     *
     * La reconnaissance passe par les codes réglés — jamais par une constante
     * lue ici : c'est `LoadingServices` qui fait autorité, et le semis s'y plie.
     * Quand il en crée un, il déclare aussi son code dans les réglages, sans
     * quoi le regroupement au dépôt ne le reconnaîtrait pas.
     */
    public function loadingServiceId(Organization $organization): string
    {
        $existing = $this->loading->serviceIds($organization);

        if ($existing !== []) {
            return $existing[0];
        }

        [$name, $unit, $minutes] = self::SERVICES['LOAD'];
        $service = $this->service($organization, 'LOAD', $name, $unit, $minutes);

        $settings = $organization->settings ?? [];
        data_set($settings, LoadingServices::SETTING_PATH, ['LOAD']);
        $organization->forceFill(['settings' => $settings])->save();

        return $service;
    }

    /**
     * La livraison, désignée par son code.
     *
     * Elle se cherchait auparavant comme « le premier service qui n'est pas un
     * chargement, par ordre de code » : avec un déballage au catalogue, c'est
     * lui que l'ordre alphabétique aurait désigné.
     */
    public function deliveryServiceId(Organization $organization): string
    {
        [$name, $unit, $minutes] = self::SERVICES['DELIVERY'];

        return $this->service($organization, 'DELIVERY', $name, $unit, $minutes);
    }

    private function service(
        Organization $organization,
        string $code,
        string $name,
        string $unit,
        int $minutes,
    ): string {
        return Service::firstOrCreate(
            ['organization_id' => $organization->id, 'code' => $code],
            [
                'name' => $name,
                'unit' => $unit,
                'default_duration_minutes' => $minutes,
                'billable_to_customer' => true,
                'payable_to_provider' => true,
                'requires_address' => true,
                'requires_contact' => false,
                'status' => 'active',
            ],
        )->id;
    }
}
