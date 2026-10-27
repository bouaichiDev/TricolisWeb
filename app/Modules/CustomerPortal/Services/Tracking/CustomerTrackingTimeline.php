<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Services\Tracking;

use App\Modules\CustomerPortal\Services\CustomerStatusLabels;
use App\Shared\Database\MorphMap;

/**
 * La timeline que lit le client : des faits compréhensibles, pas un journal.
 *
 * Chaque entrée vient d'une donnée enregistrée — création, changement de
 * statut audité, étape du parcours visible, heure planifiée ou réelle d'un
 * arrêt, preuve de livraison. Rien n'est fabriqué : sans donnée, pas d'entrée.
 *
 * `state` distingue ce qui s'est produit (`done`) de ce qui est prévu
 * (`expected`) : un passage planifié s'affiche, mais il ne se présente pas
 * comme un fait accompli.
 *
 * Pour le lien public, le nom du réceptionnaire disparaît : le client final
 * n'a pas à lire qui a signé.
 */
final readonly class CustomerTrackingTimeline
{
    public function __construct(private CustomerStatusLabels $labels) {}

    /**
     * @return list<array{type: string, title: string, description: string|null, at: string, state: string, orderServiceId: string|null}>
     */
    public function for(OrderTrackingSnapshot $snapshot, bool $public = false): array
    {
        $order = $snapshot->order;
        $items = [];

        if ($order->created_at !== null) {
            $items[] = $this->item('created', 'Commande créée', null, $order->created_at->toIso8601String());
        }

        foreach ($snapshot->statusChanges as $change) {
            $label = $this->labels->present(MorphMap::ORDER, $change['status'])['label'];

            if ($label !== null && $change['at'] !== null) {
                $items[] = $this->item('status', 'Commande : '.$label, null, $change['at']);
            }
        }

        foreach ($snapshot->journeyEvents as $event) {
            if ($event['at'] !== null) {
                $items[] = $this->item('journey', $event['title'], $event['description'], $event['at'], $event['orderServiceId']);
            }
        }

        foreach ($snapshot->services as $tracked) {
            array_push($items, ...$this->stopItems($tracked));
        }

        foreach ($snapshot->proofs as $proof) {
            if ($proof->delivered_at !== null) {
                $who = $public || $proof->recipient_name === null ? null : 'Réceptionné par '.$proof->recipient_name;
                $items[] = $this->item('proof', 'Preuve de livraison disponible', $who, $proof->delivered_at->toIso8601String(), $proof->order_service_id);
            }
        }

        usort($items, static fn (array $a, array $b): int => strcmp($a['at'], $b['at']));

        return $items;
    }

    /**
     * Passage prévu, arrivée et départ réels de l'arrêt qui porte la prestation.
     *
     * @return list<array{type: string, title: string, description: string|null, at: string, state: string, orderServiceId: string|null}>
     */
    private function stopItems(TrackedService $tracked): array
    {
        $stop = $tracked->stop;

        if ($stop === null) {
            return [];
        }

        $name = $tracked->name();
        $id = $tracked->service->id;
        $items = [];

        if ($stop->planned_arrival_at !== null && $stop->actual_arrival_at === null) {
            $items[] = $this->item('planned', $name.' : passage prévu', null, $stop->planned_arrival_at->toIso8601String(), $id, 'expected');
        }

        if ($stop->actual_arrival_at !== null) {
            $items[] = $this->item('arrived', $name.' : arrivée sur place', null, $stop->actual_arrival_at->toIso8601String(), $id);
        }

        if ($stop->actual_departure_at !== null) {
            $items[] = $this->item('departed', $name.' : passage terminé', null, $stop->actual_departure_at->toIso8601String(), $id);
        }

        return $items;
    }

    /**
     * @return array{type: string, title: string, description: string|null, at: string, state: string, orderServiceId: string|null}
     */
    private function item(string $type, string $title, ?string $description, string $at, ?string $orderServiceId = null, string $state = 'done'): array
    {
        return [
            'type' => $type,
            'title' => $title,
            'description' => $description,
            'at' => $at,
            'state' => $state,
            'orderServiceId' => $orderServiceId,
        ];
    }
}
