<?php

declare(strict_types=1);

namespace App\Modules\Billing\Services;

use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;
use Illuminate\Validation\ValidationException;

/**
 * Vérifie que les références d'une facture appartiennent bien au client facturé.
 *
 * Le §26 l'exige : un `exists:order_services,id` seul laisserait facturer à un
 * client la prestation d'un autre. Toute la chaîne est contrôlée — le client
 * dans l'organisation, la commande chez ce client, le service dans cette
 * commande.
 */
final readonly class BillingScopeGuard
{
    public function customer(string $customerId, string $organizationId): Customer
    {
        $customer = Customer::where('organization_id', $organizationId)->whereKey($customerId)->first();

        return $customer ?? $this->fail('customerId', 'Ce client n’appartient pas à l’organisation active.');
    }

    public function order(string $orderId, Customer $customer, string $field = 'orderId'): Order
    {
        $order = Order::whereKey($orderId)->where('customer_id', $customer->id)->first();

        return $order ?? $this->fail($field, 'Cette commande n’appartient pas au client de la facture.');
    }

    /**
     * Le service doit relever d'une commande du client facturé.
     */
    public function orderService(string $orderServiceId, Customer $customer, string $field = 'orderServiceId'): OrderService
    {
        $service = OrderService::whereKey($orderServiceId)
            ->whereHas('order', fn ($order) => $order->where('customer_id', $customer->id))
            ->first();

        return $service ?? $this->fail($field, 'Ce service n’appartient à aucune commande de ce client.');
    }

    /**
     * Quand les deux sont fournis, la commande doit être celle du service.
     *
     * Sans ce contrôle, une ligne pourrait désigner la commande A et le service
     * de la commande B, toutes deux du bon client — et la facture citerait une
     * référence de commande qui n'a rien à voir avec la prestation facturée.
     */
    public function assertOrderMatchesService(?string $orderId, ?OrderService $service, string $field = 'orderId'): void
    {
        if ($orderId === null || $service === null) {
            return;
        }

        if ($service->order_id !== $orderId) {
            $this->fail($field, 'Cette commande n’est pas celle du service facturé.');
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}
