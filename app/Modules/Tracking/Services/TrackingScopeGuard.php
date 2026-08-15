<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Services;

use App\Modules\Documents\Models\Document;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;
use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Models\TourStop;
use Illuminate\Validation\ValidationException;

/**
 * Vérifie que les références d'un événement de suivi ou d'une preuve de
 * livraison sont dans le périmètre et cohérentes entre elles.
 *
 * Le §18 l'exige : « ne jamais se contenter d'un simple `exists` ». Un
 * `exists:orders,id` seul laisserait passer la commande d'un autre transporteur,
 * et le service d'une autre commande. Tous les contrôles passent par ici, en un
 * seul endroit, appelé par les Actions — qui restent ainsi sûres même invoquées
 * hors HTTP.
 */
final readonly class TrackingScopeGuard
{
    public function order(string $orderId, string $organizationId): Order
    {
        $order = Order::where('organization_id', $organizationId)->whereKey($orderId)->first();

        return $order ?? $this->fail('orderId', 'Cette commande n’appartient pas à l’organisation active.');
    }

    /**
     * Le service doit relever de la commande fournie, pas seulement exister.
     */
    public function orderService(string $orderServiceId, Order $order): OrderService
    {
        $service = OrderService::whereKey($orderServiceId)->where('order_id', $order->id)->first();

        return $service ?? $this->fail('orderServiceId', 'Ce service n’appartient pas à la commande visée.');
    }

    public function tour(string $tourId, string $organizationId): Tour
    {
        $tour = Tour::where('organization_id', $organizationId)->whereKey($tourId)->first();

        return $tour ?? $this->fail('tourId', 'Cette tournée n’appartient pas à l’organisation active.');
    }

    /**
     * L'arrêt doit relever de la tournée fournie.
     *
     * Si aucune tournée n'est fournie, elle est **déduite** de l'arrêt : le
     * modèle porte déjà l'information, et obliger l'appelant à la recopier
     * n'ajouterait qu'un risque de la recopier faux. La tournée déduite est
     * ensuite vérifiée dans l'organisation.
     */
    public function tourStop(string $tourStopId, ?Tour $tour, string $organizationId): TourStop
    {
        $stop = TourStop::whereKey($tourStopId)->first();

        if ($stop === null) {
            $this->fail('tourStopId', 'Cet arrêt est introuvable.');
        }

        if ($tour !== null && $stop->tour_id !== $tour->id) {
            $this->fail('tourStopId', 'Cet arrêt n’appartient pas à la tournée fournie.');
        }

        if ($tour === null) {
            // Deduction : la tournee de l'arret doit rester dans le perimetre.
            $this->tour($stop->tour_id, $organizationId);
        }

        return $stop;
    }

    /**
     * Le document lié à une preuve doit appartenir à l'organisation de la
     * commande : une signature venue d'ailleurs ne prouve rien.
     */
    public function document(string $documentId, string $organizationId, string $field): Document
    {
        $document = Document::where('organization_id', $organizationId)->whereKey($documentId)->first();

        return $document ?? $this->fail($field, 'Ce document n’appartient pas à l’organisation de la commande.');
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}
