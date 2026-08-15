<?php

declare(strict_types=1);

namespace App\Modules\Orders\Actions;

use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;

/**
 * Recopie les services d'une commande dupliquée.
 *
 * Les données d'exécution repartent à zéro : le service revient au statut
 * initial, le temps restant est réinitialisé, et les colis servis suivent la
 * correspondance des colis dupliqués.
 */
final readonly class DuplicateOrderServices
{
    /**
     * @param  array<string, string>  $packageMap  ancien identifiant de colis => nouveau
     */
    public function execute(Order $source, Order $copy, array $packageMap, bool $withContacts): void
    {
        foreach ($source->orderServices()->with(['contacts', 'servicePackages'])->get() as $service) {
            $new = $copy->orderServices()->create($this->attributes($service));

            if ($withContacts) {
                $this->copyContacts($service, $new);
            }

            $this->copyPackages($service, $new, $packageMap);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(OrderService $service): array
    {
        return $service->only([
            'service_id', 'address_id', 'sequence', 'requested_date', 'requested_from', 'requested_to',
            'quantity', 'unit', 'required_time_minutes', 'weight', 'volume', 'package_count',
            'customer_unit_price', 'customer_total_price', 'provider_unit_cost', 'provider_total_cost', 'instructions',
        ]) + [
            'service_number' => $service->service_number,
            'remaining_time_minutes' => $service->required_time_minutes,
            'status' => 'draft',
        ];
    }

    private function copyContacts(OrderService $source, OrderService $copy): void
    {
        foreach ($source->contacts as $contact) {
            $copy->contacts()->create($contact->only([
                'contact_id', 'contact_role', 'first_name_snapshot', 'last_name_snapshot',
                'phone_snapshot', 'mobile_snapshot', 'email_snapshot', 'is_primary',
            ]));
        }
    }

    /**
     * @param  array<string, string>  $packageMap
     */
    private function copyPackages(OrderService $source, OrderService $copy, array $packageMap): void
    {
        foreach ($source->servicePackages as $link) {
            if (! isset($packageMap[$link->package_id])) {
                continue;
            }

            $copy->servicePackages()->create([
                'package_id' => $packageMap[$link->package_id],
                'quantity' => $link->quantity,
                'handling_instructions' => $link->handling_instructions,
                'status' => 'pending',
            ]);
        }
    }
}
