<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Services;

use App\Modules\Orders\Models\OrderService;

/**
 * Les valeurs qu'une prestation sait rendre, et d'où elles viennent.
 *
 * **Le registre est en code, et c'est délibéré.** Le §67 interdit un chemin
 * arbitraire vers le modèle : laisser saisir « table + colonne » ouvrirait la
 * lecture de n'importe quelle colonne — un jeton, un mot de passe — et
 * resterait de toute façon incomplet, puisqu'il faudrait aussi le chemin de
 * relation depuis la prestation.
 *
 * Le superadmin garde donc la main sur **quelles** sources deviennent des
 * variables et sous quel nom ; le code reste seul juge de ce qui est lisible.
 * Ajouter une source vraiment nouvelle est une modification de code, parce
 * qu'aller la chercher en est une.
 *
 * Chaque entrée annonce sa table et sa colonne : le superadmin voit d'où sort
 * la valeur qu'il expose, plutôt qu'un identifiant opaque.
 */
final readonly class PricingVariableSources
{
    public const string NUMERIC = 'numeric';

    public const string DIMENSION = 'dimension';

    public function __construct(private DepotDistance $distance) {}

    /**
     * Le catalogue des sources lisibles.
     *
     * @return array<string, array{table: string, column: string, kind: string, label: string}>
     */
    public static function all(): array
    {
        return [
            'order_service.weight' => [
                'table' => 'order_services', 'column' => 'weight',
                'kind' => self::NUMERIC, 'label' => 'Poids de la prestation',
            ],
            'order_service.volume' => [
                'table' => 'order_services', 'column' => 'volume',
                'kind' => self::NUMERIC, 'label' => 'Volume de la prestation',
            ],
            'order_service.quantity' => [
                'table' => 'order_services', 'column' => 'quantity',
                'kind' => self::NUMERIC, 'label' => 'Quantité de la prestation',
            ],
            'order_service.package_count' => [
                'table' => 'order_services', 'column' => 'package_count',
                'kind' => self::NUMERIC, 'label' => 'Nombre de colis de la prestation',
            ],
            'order_service.required_time_minutes' => [
                'table' => 'order_services', 'column' => 'required_time_minutes',
                'kind' => self::NUMERIC, 'label' => 'Durée prévue de la prestation',
            ],

            // La commande entiere : utile pour un forfait, dangereux pour une
            // ligne — le §169K rappelle qu'un chargement et une livraison
            // partagent le poids de la commande, et le facturer deux fois est
            // une erreur qui ne se voit pas.
            'order.weight' => [
                'table' => 'orders', 'column' => 'weight',
                'kind' => self::NUMERIC, 'label' => 'Poids total de la commande',
            ],
            'order.volume' => [
                'table' => 'orders', 'column' => 'volume',
                'kind' => self::NUMERIC, 'label' => 'Volume total de la commande',
            ],
            'order.package_count' => [
                'table' => 'orders', 'column' => 'package_count',
                'kind' => self::NUMERIC, 'label' => 'Nombre de colis de la commande',
            ],

            'depot.distance_km' => [
                'table' => 'addresses', 'column' => 'latitude, longitude',
                'kind' => self::NUMERIC, 'label' => 'Distance dépôt → prestation, en km',
            ],

            'address.postal_code' => [
                'table' => 'addresses', 'column' => 'postal_code',
                'kind' => self::DIMENSION, 'label' => 'Code postal de la prestation',
            ],
            'address.city' => [
                'table' => 'addresses', 'column' => 'city',
                'kind' => self::DIMENSION, 'label' => 'Ville de la prestation',
            ],
            'address.country' => [
                'table' => 'addresses', 'column' => 'country',
                'kind' => self::DIMENSION, 'label' => 'Pays de la prestation',
            ],
            'service.code' => [
                'table' => 'services', 'column' => 'code',
                'kind' => self::DIMENSION, 'label' => 'Code de la prestation',
            ],
        ];
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    /**
     * La valeur d'une source pour cette prestation.
     *
     * Une source inconnue rend `null` plutôt que de lever : une variable dont
     * la source aurait disparu d'une version à l'autre ne doit pas empêcher
     * toute facturation — la formule qui l'emploie échouera, elle seule.
     */
    public function value(string $sourceKey, OrderService $service): ?string
    {
        $address = $service->address;

        return match ($sourceKey) {
            'order_service.weight' => $this->number($service->weight),
            'order_service.volume' => $this->number($service->volume),
            'order_service.quantity' => $this->number($service->quantity),
            'order_service.package_count' => $this->number($service->package_count),
            'order_service.required_time_minutes' => $this->number($service->required_time_minutes),

            'order.weight' => $this->number($service->order?->weight),
            'order.volume' => $this->number($service->order?->volume),
            'order.package_count' => $this->number($service->order?->package_count),

            'depot.distance_km' => $this->distance->kilometres($service),

            'address.postal_code' => $address?->postal_code,
            'address.city' => $address?->city,
            'address.country' => $address?->country,
            'service.code' => $service->service?->code,

            default => null,
        };
    }

    private function number(mixed $value): ?string
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return (string) $value;
    }
}
