<?php

declare(strict_types=1);

namespace App\Modules\Orders\Actions;

use App\Modules\Contacts\Models\Contact;
use App\Modules\Orders\DTOs\CreateOrderServiceData;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;
use App\Modules\Orders\Models\OrderServiceContact;
use App\Modules\Orders\Services\OrderScopeGuard;
use App\Modules\Packages\Models\Package;
use Illuminate\Validation\ValidationException;

/**
 * Crée les services d'une commande, avec leurs contacts et leurs colis.
 *
 * Le service porte son adresse et son créneau : c'est l'unité de planification
 * du modèle. Les contacts sont figés au moment de la création, pour qu'une
 * modification ultérieure du contact partagé n'altère pas la commande.
 */
final readonly class CreateOrderServices
{
    public function __construct(private OrderScopeGuard $guard) {}

    /**
     * @param  list<CreateOrderServiceData>  $services
     * @param  array<string, Package>  $packages
     * @return list<OrderService>
     */
    public function execute(Order $order, array $services, array $packages): array
    {
        $created = [];

        foreach ($services as $index => $service) {
            $attributes = $service->attributes;
            $attributes['service_id'] = $this->guard
                ->service($service->serviceId, $order->organization_id, "services.$index.serviceId")->id;
            $attributes['address_id'] = $this->guard
                ->address($service->addressId, $order->organization_id, "services.$index.addressId")->id;

            $model = $order->orderServices()->create($attributes);

            $this->attachContacts($model, $service, $index);
            $this->attachPackages($model, $service, $packages, $index);

            $created[] = $model;
        }

        return $created;
    }

    private function attachContacts(OrderService $service, CreateOrderServiceData $data, int $index): void
    {
        foreach ($data->contacts as $position => $contact) {
            $attributes = [
                'contact_role' => $contact['contactRole'] ?? 'other',
                'is_primary' => $contact['isPrimary'] ?? false,
                'first_name_snapshot' => $contact['firstName'] ?? null,
                'last_name_snapshot' => $contact['lastName'] ?? null,
                'phone_snapshot' => $contact['phone'] ?? null,
                'mobile_snapshot' => $contact['mobile'] ?? null,
                'email_snapshot' => $contact['email'] ?? null,
            ];

            if (isset($contact['contactId'])) {
                $shared = Contact::whereKey($contact['contactId'])
                    ->whereHas('entityContacts', fn ($query) => $query->where('organization_id', $service->order->organization_id))
                    ->first();

                if ($shared === null) {
                    throw ValidationException::withMessages([
                        "services.$index.contacts.$position.contactId" => ['Ce contact n’est pas accessible dans l’organisation active.'],
                    ]);
                }

                $attributes['contact_id'] = $shared->id;
                // Les valeurs explicites du payload priment sur le contact partagé.
                $attributes = array_merge(OrderServiceContact::snapshotFrom($shared), array_filter($attributes, static fn ($v): bool => $v !== null));
            }

            $service->contacts()->create($attributes);
        }
    }

    /**
     * @param  array<string, Package>  $packages
     */
    private function attachPackages(OrderService $service, CreateOrderServiceData $data, array $packages, int $index): void
    {
        foreach ($data->packages as $position => $link) {
            $key = $link['packageKey'] ?? $link['packageId'];

            if ($key === null || ! isset($packages[$key])) {
                throw ValidationException::withMessages([
                    "services.$index.packages.$position.packageKey" => ['Ce colis est introuvable dans le payload.'],
                ]);
            }

            $service->servicePackages()->create([
                'package_id' => $packages[$key]->id,
                'quantity' => $link['quantity'],
                'handling_instructions' => $link['handlingInstructions'],
                'status' => $link['status'] ?? 'pending',
            ]);
        }
    }
}
