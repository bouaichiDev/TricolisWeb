<?php

declare(strict_types=1);

namespace App\Modules\Claims\Services;

use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;
use App\Modules\Tours\Models\Tour;
use Illuminate\Validation\ValidationException;

/**
 * Vérifie les références d'une réclamation (§16).
 *
 * La chaîne est plus longue qu'ailleurs : le client doit être dans
 * l'organisation, la commande chez ce client, le service dans cette commande —
 * ou à défaut dans une commande du même client, ce que le §16 autorise
 * explicitement pour les réclamations visant une prestation sans commande
 * précise.
 */
final readonly class ClaimScopeGuard
{
    public function customer(string $customerId, string $organizationId): Customer
    {
        $customer = Customer::where('organization_id', $organizationId)->whereKey($customerId)->first();

        return $customer ?? $this->fail('customerId', 'Ce client n’appartient pas à l’organisation active.');
    }

    public function order(string $orderId, Customer $customer): Order
    {
        $order = Order::whereKey($orderId)->where('customer_id', $customer->id)->first();

        return $order ?? $this->fail('orderId', 'Cette commande n’appartient pas au client de la réclamation.');
    }

    /**
     * Avec une commande, le service doit lui appartenir. Sans commande, il doit
     * relever d'une commande du même client.
     */
    public function orderService(string $orderServiceId, ?Order $order, Customer $customer): OrderService
    {
        $service = OrderService::whereKey($orderServiceId)
            ->when(
                $order !== null,
                fn ($query) => $query->where('order_id', $order->id),
                fn ($query) => $query->whereHas('order', fn ($o) => $o->where('customer_id', $customer->id)),
            )
            ->first();

        return $service ?? $this->fail(
            'orderServiceId',
            $order !== null
                ? 'Ce service n’appartient pas à la commande de la réclamation.'
                : 'Ce service n’appartient à aucune commande de ce client.',
        );
    }

    public function tour(string $tourId, string $organizationId): Tour
    {
        $tour = Tour::where('organization_id', $organizationId)->whereKey($tourId)->first();

        return $tour ?? $this->fail('tourId', 'Cette tournée n’appartient pas à l’organisation active.');
    }

    /**
     * Le responsable doit être membre de l'organisation : on n'affecte pas un
     * dossier à quelqu'un qui n'y a pas accès.
     */
    public function user(string $userId, string $organizationId, string $field = 'responsibleUserId'): User
    {
        $user = User::whereKey($userId)
            ->whereHas('organizationUsers', fn ($query) => $query->where('organization_id', $organizationId))
            ->first();

        return $user ?? $this->fail($field, 'Cet utilisateur n’est pas accessible dans l’organisation active.');
    }

    /**
     * Une réclamation ne peut pas être clôturée avant d'avoir été ouverte.
     */
    public function assertClosedAtIsCoherent(?string $closedAt, \DateTimeInterface $createdAt): void
    {
        if ($closedAt === null) {
            return;
        }

        if (strtotime($closedAt) < $createdAt->getTimestamp()) {
            $this->fail('closedAt', 'La date de clôture ne peut pas précéder la date de création.');
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}
