<?php

declare(strict_types=1);

namespace App\Modules\DeliveryNotes\Services;

use App\Modules\Orders\Models\OrderService;

/**
 * Les données qu'un modèle de bon de livraison a le droit de nommer.
 *
 * Le contexte est **le même pour tous les modèles de BL** : un modèle qui n'en
 * nomme que la moitié est normal, pas fautif. C'est pourquoi le rendu passe par
 * `renderDocument()` et non par `render()`, qui refuserait toute valeur non
 * employée.
 *
 * Sept blocs, tous à plat sauf deux listes :
 *
 * - `deliveryNote` — le document lui-même : son numéro, sa date ;
 * - `organization` — l'en-tête, logo compris ;
 * - `orderer` — le donneur d'ordre ;
 * - `order` et `service` — la commande et la prestation ;
 * - `loading` et `delivery` — chargement et livraison ;
 * - `packages` et `articles` — les deux listes répétables ;
 * - `totals` — ce que les deux tableaux additionnent.
 *
 * `internal_remark` de la commande **n'y figure pas** : c'est une note interne
 * au transporteur, et un BL se remet au destinataire. `worker_remark` s'y
 * trouve sous `order.remark` — elle est écrite pour celui qui exécute, et se
 * lit sur le terrain.
 */
final readonly class DeliveryNoteRenderContext
{
    public function __construct(
        private DeliveryNoteParties $parties,
        private DeliveryNoteContents $contents,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(OrderService $service, string $number): array
    {
        $service->loadMissing([
            'order.organization', 'order.customer', 'order.agency', 'order.depot',
            'service', 'address', 'contacts',
        ]);

        $packages = $this->contents->packages($service);
        $articles = $this->contents->articles($service);

        return [
            'deliveryNote' => [
                'number' => $number,
                'issuedAt' => now()->toDateString(),
                'issuedAtTime' => now()->format('H:i'),
            ],
            'organization' => $this->parties->organization($service),
            'orderer' => $this->parties->orderer($service),
            'order' => $this->order($service),
            'service' => $this->service($service),
            'loading' => $this->parties->loading($service),
            'delivery' => $this->parties->delivery($service),
            'packages' => $packages,
            'articles' => $articles,
            'totals' => $this->contents->totals($packages, $articles),
        ];
    }

    /**
     * @return array<string, scalar|null>
     */
    private function order(OrderService $service): array
    {
        $order = $service->order;

        return [
            'orderNumber' => $order?->order_number,
            'orderDate' => $order?->order_date?->toDateString(),
            'orderType' => $order?->order_type,
            'externalReference' => $order?->external_reference,
            'customerReference' => $order?->customer_reference,
            'groupCode' => $order?->group_code,
            'currencyCode' => $order?->currency_code,
            'remark' => $order?->worker_remark,
        ];
    }

    /**
     * @return array<string, scalar|null>
     */
    private function service(OrderService $service): array
    {
        return [
            'serviceNumber' => $service->service_number,
            'code' => $service->service?->code,
            'name' => $service->service?->name,
            'sequence' => $service->sequence,
            'quantity' => $this->decimal($service->quantity, 3),
            'unit' => $service->unit,
            'requestedDate' => $service->requested_date?->toDateString(),
            'requestedFrom' => $service->requested_from?->format('H:i'),
            'requestedTo' => $service->requested_to?->format('H:i'),
            'weight' => $this->decimal($service->weight, 3),
            'volume' => $this->decimal($service->volume, 4),
            'packageCount' => $service->package_count,
            'instructions' => $service->instructions,
            'status' => $service->status?->value,
        ];
    }

    private function decimal(mixed $value, int $scale): ?string
    {
        return $value === null ? null : number_format((float) $value, $scale, '.', '');
    }
}
