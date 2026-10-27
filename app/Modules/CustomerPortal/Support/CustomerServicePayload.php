<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Support;

use App\Modules\CustomerPortal\Exceptions\CustomerOrderException;
use App\Modules\CustomerPortal\Services\CustomerAddressBook;
use App\Modules\Customers\Models\Customer;
use App\Modules\Integrations\Services\ImportedRecipient;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\Service;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Les services d'une commande du portail, complétés de ce que le client ne
 * saisit pas : numéro et rang, unité et durée tirées du référentiel, mesures
 * déduites des colis rattachés.
 *
 * L'adresse est soit une adresse de son carnet (`addressId`), soit un
 * destinataire saisi pour l'occasion (`address`) — créé comme le fait déjà
 * l'import (`ImportedRecipient`), hors du carnet du client.
 */
final readonly class CustomerServicePayload
{
    public function __construct(
        private CustomerAddressBook $addressBook,
        private ImportedRecipient $recipients,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $services
     * @param  list<array<string, mixed>>  $packages  colis déjà traduits, indexés par `key`
     * @return list<array<string, mixed>>
     */
    public function map(array $services, array $packages, Customer $customer, ?Order $order = null): array
    {
        $catalog = $this->catalog($services, $customer);
        $byKey = collect($packages)->keyBy('key');
        $result = [];

        foreach (array_values($services) as $index => $service) {
            $model = $catalog->get($service['serviceId']);

            if ($model === null) {
                throw ValidationException::withMessages([
                    "services.$index.serviceId" => ['Ce service n’est pas proposé par votre transporteur.'],
                ]);
            }

            [$addressId, $recipientContact] = $this->address($service, $index, $customer, $order);
            $links = $this->packageLinks($service['packages'] ?? []);
            $linked = $byKey->only(array_column($links, 'packageKey'));
            $minutes = (int) ($model->default_duration_minutes ?? 0);

            $result[] = [
                'serviceId' => $model->id,
                'addressId' => $addressId,
                'serviceNumber' => 'S'.($index + 1),
                'sequence' => $index + 1,
                'requestedDate' => $service['requestedDate'],
                'requestedFrom' => $this->at($service['requestedDate'], $service['requestedFrom'] ?? null),
                'requestedTo' => $this->at($service['requestedDate'], $service['requestedTo'] ?? null),
                'quantity' => 1,
                'unit' => $model->unit ?? 'unit',
                'requiredTimeMinutes' => $minutes,
                'remainingTimeMinutes' => $minutes,
                'weight' => $linked->sum(static fn ($p): float => (float) ($p['weight'] ?? 0)),
                'volume' => $linked->sum(static fn ($p): float => (float) ($p['volume'] ?? 0)),
                'packageCount' => count($links),
                'instructions' => $service['instructions'] ?? null,
                'contacts' => $this->contacts($service['contacts'] ?? [], $recipientContact),
                'packages' => $links,
            ];
        }

        return $result;
    }

    /**
     * @param  list<array<string, mixed>>  $services
     * @return Collection<string, Service>
     */
    private function catalog(array $services, Customer $customer): Collection
    {
        return Service::where('organization_id', $customer->organization_id)
            ->where('status', 'active')
            ->whereIn('id', array_column($services, 'serviceId'))
            ->get()
            ->keyBy('id');
    }

    /**
     * @param  array<string, mixed>  $service
     * @return array{0: string, 1: array<string, mixed>|null}
     */
    private function address(array $service, int $index, Customer $customer, ?Order $order): array
    {
        if (! empty($service['addressId'])) {
            if (! $this->addressBook->usable($service['addressId'], $customer, $order)) {
                throw CustomerOrderException::invalidAddress("services.$index.addressId");
            }

            return [$service['addressId'], null];
        }

        $recipient = $this->recipients->create($service['address'], $customer->organization_id);

        return [$recipient['addressId'], $recipient['contact']];
    }

    /**
     * Les contacts ne sont que des copies figées : le portail n'en désigne
     * aucun de l'annuaire du transporteur par identifiant.
     *
     * @param  list<array<string, mixed>>  $contacts
     * @param  array<string, mixed>|null  $recipientContact
     * @return list<array<string, mixed>>
     */
    private function contacts(array $contacts, ?array $recipientContact): array
    {
        if ($contacts === [] && $recipientContact !== null) {
            return [$recipientContact];
        }

        $mapped = array_map(static fn (array $contact): array => [
            'contactRole' => $contact['contactRole'] ?? 'delivery',
            'isPrimary' => (bool) ($contact['isPrimary'] ?? false),
            'firstName' => $contact['firstName'],
            'lastName' => $contact['lastName'] ?? null,
            'phone' => $contact['phone'] ?? null,
            'mobile' => $contact['mobile'] ?? null,
            'email' => $contact['email'] ?? null,
        ], array_values($contacts));

        if ($mapped !== [] && ! in_array(true, array_column($mapped, 'isPrimary'), true)) {
            $mapped[0]['isPrimary'] = true;
        }

        return $mapped;
    }

    /**
     * @param  list<array<string, mixed>>  $links
     * @return list<array{packageKey: string, quantity: float}>
     */
    private function packageLinks(array $links): array
    {
        return array_map(static fn (array $link): array => [
            'packageKey' => (string) $link['packageKey'],
            'quantity' => (float) ($link['quantity'] ?? 1),
        ], array_values($links));
    }

    private function at(string $date, ?string $time): ?string
    {
        return $time === null || $time === '' ? null : substr($date, 0, 10).' '.$time.':00';
    }
}
