<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

/**
 * Carnet d'adresses suisse pour les jeux de démonstration.
 *
 * **Les coordonnées sont réelles**, relevées au centre de chaque localité. C'est
 * ce qui permet à la carte et au calcul d'itinéraire de montrer quelque chose
 * sans appeler le service de géocodage neuf cents fois — le quota n'y
 * survivrait pas, et une adresse inventée ne se géocode de toute façon pas.
 *
 * Le numéro de rue et un léger décalage distinguent deux commandes d'une même
 * ville : sans lui, trente points se superposeraient au même pixel et la
 * distance entre eux vaudrait zéro. Le décalage reste sous deux kilomètres, et
 * il est **déterministe** — deux semis produisent la même carte.
 */
final class SwissAddressBook
{
    /**
     * Localités suisses : code postal, ville, canton, latitude, longitude.
     *
     * @var list<array{0: string, 1: string, 2: string, 3: float, 4: float}>
     */
    private const array LOCALITIES = [
        ['1200', 'Genève', 'GE', 46.2044, 6.1432],
        ['1003', 'Lausanne', 'VD', 46.5197, 6.6323],
        ['8001', 'Zürich', 'ZH', 47.3769, 8.5417],
        ['3011', 'Berne', 'BE', 46.9480, 7.4474],
        ['4051', 'Bâle', 'BS', 47.5596, 7.5886],
        ['6003', 'Lucerne', 'LU', 47.0502, 8.3093],
        ['8400', 'Winterthour', 'ZH', 47.5001, 8.7501],
        ['9000', 'Saint-Gall', 'SG', 47.4245, 9.3767],
        ['6900', 'Lugano', 'TI', 46.0037, 8.9511],
        ['2502', 'Bienne', 'BE', 47.1368, 7.2468],
        ['3600', 'Thoune', 'BE', 46.7580, 7.6280],
        ['1700', 'Fribourg', 'FR', 46.8065, 7.1615],
        ['8200', 'Schaffhouse', 'SH', 47.6960, 8.6349],
        ['7000', 'Coire', 'GR', 46.8508, 9.5320],
        ['2000', 'Neuchâtel', 'NE', 46.9900, 6.9293],
        ['1950', 'Sion', 'VS', 46.2331, 7.3606],
        ['6300', 'Zoug', 'ZG', 47.1662, 8.5155],
        ['1400', 'Yverdon-les-Bains', 'VD', 46.7785, 6.6410],
        ['1820', 'Montreux', 'VD', 46.4312, 6.9107],
        ['8500', 'Frauenfeld', 'TG', 47.5536, 8.8985],
        ['1800', 'Vevey', 'VD', 46.4628, 6.8419],
        ['1260', 'Nyon', 'VD', 46.3833, 6.2333],
        ['1110', 'Morges', 'VD', 46.5113, 6.4981],
        ['1630', 'Bulle', 'FR', 46.6193, 7.0570],
        ['1920', 'Martigny', 'VS', 46.1028, 7.0722],
        ['5000', 'Aarau', 'AG', 47.3925, 8.0442],
        ['4600', 'Olten', 'SO', 47.3500, 7.9039],
        ['4500', 'Soleure', 'SO', 47.2088, 7.5323],
        ['5400', 'Baden', 'AG', 47.4735, 8.3063],
        ['8640', 'Rapperswil', 'SG', 47.2260, 8.8180],
    ];

    /** @var list<string> */
    private const array STREETS = [
        'Route de la Gare', 'Avenue des Alpes', 'Rue du Marché', 'Chemin des Vignes',
        'Bahnhofstrasse', 'Industriestrasse', 'Route Cantonale', 'Rue Centrale',
        'Hauptstrasse', 'Chemin du Lac',
    ];

    /**
     * Adresse numéro `$index`, telle qu'`addresses` l'attend.
     *
     * @return array<string, mixed>
     */
    public static function at(int $index): array
    {
        [$postalCode, $city, $canton, $latitude, $longitude] = self::LOCALITIES[$index % count(self::LOCALITIES)];

        $street = self::STREETS[intdiv($index, count(self::LOCALITIES)) % count(self::STREETS)];
        $number = 1 + ($index * 7) % 180;

        return [
            'name' => sprintf('%s — %s', $city, $canton),
            'address_number' => (string) $number,
            'route' => $street,
            'address_line_1' => sprintf('%d %s', $number, $street),
            'postal_code' => $postalCode,
            'city' => $city,
            'country' => 'CH',
            'latitude' => round($latitude + self::offset($index, 3), 8),
            'longitude' => round($longitude + self::offset($index, 5), 8),
            'status' => 'active',
        ];
    }

    /** Nombre de localités couvertes, pour dimensionner un jeu de clients. */
    public static function localityCount(): int
    {
        return count(self::LOCALITIES);
    }

    /**
     * Décalage déterministe, sous deux kilomètres.
     *
     * Ni `rand()` ni `faker` : un semis rejoué doit reproduire la même carte,
     * sans quoi comparer deux exécutions devient impossible.
     */
    private static function offset(int $index, int $salt): float
    {
        return ((($index * $salt) % 37) - 18) * 0.001;
    }
}
