<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Actions;

use App\Modules\Addresses\Models\Address;
use App\Modules\Orders\Models\Order;
use App\Modules\Planning\Services\BatchGeocoder;

/**
 * Situe les adresses d'un import, **tout de suite** — dans la limite du temps.
 *
 * Une commande créée à la main géocode en file : le formulaire n'a pas à
 * attendre le service distant. Un import est un autre cas. Ses destinataires
 * n'existaient nulle part une seconde plus tôt, et c'est souvent pour les
 * planifier dans la foulée qu'on importe.
 *
 * **Après l'écriture, jamais pendant.** Un fichier refusé aurait consommé des
 * appels pour des adresses que la transaction efface.
 *
 * **Le budget protège la réponse.** Les commandes sont déjà écrites quand le
 * géocodage commence : si PHP coupait la requête ici, l'écran annoncerait un
 * échec pour un import réussi, et l'utilisateur réimporterait des doublons.
 * Ce qui ne tient pas dans le budget revient `pending` ; le Job mis en file par
 * `CreateFullOrder` s'en charge.
 */
final readonly class LocateImportedAddresses
{
    /** Secondes : très en deçà des 30 s de `max_execution_time`. */
    public const float BUDGET_SECONDS = 12.0;

    public function __construct(
        private BatchGeocoder $geocoder,
        private float $budgetSeconds = self::BUDGET_SECONDS,
    ) {}

    /**
     * @param  list<Order>  $orders
     * @return array{located: int, unlocated: int, pending: int}
     */
    public function execute(array $orders, string $organizationId): array
    {
        $ids = [];

        foreach ($orders as $order) {
            foreach ($order->orderServices()->pluck('address_id') as $id) {
                $ids[(string) $id] = true;
            }
        }

        return $this->geocoder->locate(
            Address::whereIn('id', array_keys($ids))->get(),
            $organizationId,
            $this->budgetSeconds,
        );
    }
}
