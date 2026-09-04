<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderLine;
use App\Modules\Packages\Models\Package;

/**
 * Ce qu'une commande de démonstration contient : des articles et des colis.
 *
 * **Les deux varient avec le rang.** Un semis où chaque commande pèse la même
 * chose et porte le même nombre de colis ne dit rien d'un camion qui se remplit
 * : les totaux d'une tournée y seraient toujours des multiples du même nombre,
 * et une contrainte de charge ne se déclencherait jamais. Un à trois colis, un à
 * trois articles, poids et volumes distincts par gabarit.
 *
 * Les adresses et les contacts n'y sont plus : ils appartiennent au client, pas
 * à la commande. {@see SwissCustomerBook} les tient.
 */
final readonly class SwissOrderParts
{
    /**
     * Les gabarits de colis : description, poids, volume.
     *
     * @var list<array{0: string, 1: float, 2: float}>
     */
    private const array PACKAGE_KINDS = [
        ['Carton standard', 12.5, 0.08],
        ['Palette europe', 180.0, 0.96],
        ['Colis long', 34.0, 0.42],
    ];

    /**
     * Les articles disponibles : code, nom, prix de vente.
     *
     * @var list<array{0: string, 1: string, 2: float}>
     */
    private const array ARTICLES = [
        ['ART-CH-001', 'Meuble en kit', 249.90],
        ['ART-CH-002', 'Accessoires de montage', 39.90],
        ['ART-CH-003', 'Plan de travail', 189.00],
    ];

    /** Un à trois colis, selon le rang de la commande. */
    public static function packageCount(int $index): int
    {
        return 1 + $index % 3;
    }

    /**
     * Les colis de la commande.
     *
     * @return list<Package>
     */
    public function packages(Order $order, int $index): array
    {
        $packages = [];

        for ($position = 1; $position <= self::packageCount($index); $position++) {
            [$description, $weight, $volume] = self::PACKAGE_KINDS[($index + $position) % count(self::PACKAGE_KINDS)];

            $packages[] = Package::create([
                'order_id' => $order->id,
                'reference' => sprintf('%s-C%d', $order->order_number, $position),
                'description' => $description,
                'quantity' => 1,
                'weight' => $weight,
                'volume' => $volume,
                'status' => 'ready',
            ]);
        }

        return $packages;
    }

    /**
     * Le poids et le volume que porteront la commande et ses prestations.
     *
     * Calculés depuis les mêmes gabarits que les colis, et **avant** de les
     * créer : la commande porte ses totaux dès l'insertion, sans quoi il
     * faudrait la relire pour les poser.
     *
     * @return array{0: float, 1: float}
     */
    public static function totals(int $index): array
    {
        $weight = 0.0;
        $volume = 0.0;

        for ($position = 1; $position <= self::packageCount($index); $position++) {
            [, $packageWeight, $packageVolume] = self::PACKAGE_KINDS[($index + $position) % count(self::PACKAGE_KINDS)];

            $weight += $packageWeight;
            $volume += $packageVolume;
        }

        return [round($weight, 3), round($volume, 4)];
    }

    /** Un à trois articles, pris dans le catalogue par rotation. */
    public function lines(Order $order, int $index): void
    {
        $count = 1 + ($index + 1) % 3;

        for ($position = 0; $position < $count; $position++) {
            [$code, $name, $price] = self::ARTICLES[($index + $position) % count(self::ARTICLES)];

            OrderLine::create([
                'order_id' => $order->id,
                'article_code' => $code,
                'name' => $name,
                'quantity' => 1 + ($index + $position) % 4,
                'weight' => 6.25,
                'volume' => 0.04,
                'selling_price' => $price,
                'status' => 'active',
            ]);
        }
    }
}
