<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

/**
 * Combien de prestations porte une commande, et où chacune se fait.
 *
 * **Une commande n'en porte pas toujours deux.** Un simple dépôt de colis en
 * compte une ; une livraison de meuble en compte quatre — charger, livrer,
 * monter, débarrasser l'emballage. Un semis qui n'en produirait qu'une forme ne
 * dirait rien de ce que la planification doit savoir faire : cumuler des durées
 * inégales sur un arrêt, facturer des lignes différentes, laisser un service
 * partir sans les autres.
 *
 * Les quatre combinaisons tournent par le rang de la commande — pas au hasard :
 * deux semis rejoués doivent produire le même jeu, sinon comparer deux
 * exécutions devient impossible.
 *
 * **Le chargement reste au dépôt.** C'est son adresse qui le fait rejoindre
 * celui des autres commandes du jour, donc remonter en tête de tournée ; le
 * poser chez le client éclaterait le départ en autant d'arrêts que de clients.
 * L'adresse de chargement du client existe malgré tout dans son carnet : elle
 * sert à qui compose une commande à la main ou par import.
 */
final readonly class SwissServiceMix
{
    /**
     * De une à quatre prestations, dans l'ordre où elles s'exécutent.
     *
     * @var list<list<string>>
     */
    private const array COMBINATIONS = [
        ['DELIVERY'],
        ['LOAD', 'DELIVERY'],
        ['LOAD', 'DELIVERY', 'MONTAGE'],
        ['LOAD', 'DELIVERY', 'MONTAGE', 'DEBALLAGE'],
    ];

    /**
     * @param  array<string, string>  $serviceIds  code → identifiant
     * @param  array<string, int>  $minutes  code → durée par défaut
     */
    public function __construct(
        private array $serviceIds,
        private array $minutes,
        private string $depotAddressId,
    ) {}

    /**
     * Les prestations de la commande de rang `$index`.
     *
     * @return list<array{code: string, serviceId: string, addressId: string, minutes: int, contactRole: string|null}>
     */
    public function for(int $index, SeededCustomer $customer): array
    {
        $codes = self::COMBINATIONS[$index % count(self::COMBINATIONS)];

        $services = [];

        foreach ($codes as $code) {
            $services[] = [
                'code' => $code,
                'serviceId' => $this->serviceIds[$code],
                'addressId' => $code === 'LOAD'
                    ? $this->depotAddressId
                    : $customer->addressFor('delivery'),
                'minutes' => $this->minutes[$code],
                // Le contact ne se répète pas sur chaque prestation : c'est la
                // livraison qui appelle, et prévenir quatre fois la même
                // personne pour une commande ferait quatre appels.
                'contactRole' => $code === 'DELIVERY' ? 'delivery' : null,
            ];
        }

        return $services;
    }
}
