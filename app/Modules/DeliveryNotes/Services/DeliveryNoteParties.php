<?php

declare(strict_types=1);

namespace App\Modules\DeliveryNotes\Services;

use App\Modules\Addresses\Models\Address;
use App\Modules\Addresses\Models\EntityAddress;
use App\Modules\Orders\Models\OrderService;
use App\Modules\Organizations\Services\OrganizationLogo;
use App\Shared\Database\MorphMap;

/**
 * Les quatre parties d'un bon de livraison : l'en-tête, le donneur d'ordre,
 * le chargement et la livraison.
 *
 * **Une liste close, écrite à la main.** Rien n'est lu par réflexion : un
 * modèle pouvant nommer `customer.paymentMode` ferait apparaître sur le BL
 * remis au destinataire ce qui ne regarde que la comptabilité.
 *
 * Le **chargement** est le point de départ de la marchandise : l'agence qui
 * exécute la commande, son point de chargement déclaré et son dépôt. Ce n'est
 * pas une adresse portée par la commande — le modèle n'en a pas — mais celle
 * rattachée à l'agence, prise par défaut.
 *
 * La **livraison** est l'adresse du service lui-même, avec son contact
 * principal. C'est le service qui porte l'adresse dans ce modèle, pas la
 * commande : deux services d'une même commande ne se livrent pas au même
 * endroit, et un BL par service serait faux s'il lisait la commande.
 */
final readonly class DeliveryNoteParties
{
    public function __construct(private OrganizationLogo $logo) {}

    /**
     * L'en-tête : qui édite le document.
     *
     * `logo` fait exception à la règle des chaînes courtes — c'est le fichier
     * entier, encodé. Il s'écrit `<img src="{{ organization.logo }}">`, et le
     * document se suffit alors à lui-même : dompdf n'a ni session ni réseau
     * pour aller chercher une URL au moment du rendu.
     *
     * @return array<string, scalar|null>
     */
    public function organization(OrderService $service): array
    {
        $organization = $service->order?->organization;

        return [
            'code' => $organization?->code,
            'name' => $organization?->name,
            'legalName' => $organization?->legal_name,
            'registrationNumber' => $organization?->registration_number,
            'taxNumber' => $organization?->tax_number,
            'email' => $organization?->email,
            'phone' => $organization?->phone,
            'logo' => $this->logo->dataUri($organization),
        ];
    }

    /**
     * Le donneur d'ordre : le client de la commande, celui qui commande le
     * transport — pas nécessairement celui qui reçoit.
     *
     * @return array<string, scalar|null>
     */
    public function orderer(OrderService $service): array
    {
        $customer = $service->order?->customer;

        return [
            'code' => $customer?->code,
            'name' => $customer?->name,
            'legalName' => $customer?->legal_name,
            'email' => $customer?->email,
            'phone' => $customer?->phone,
        ];
    }

    /**
     * @return array<string, scalar|null>
     */
    public function loading(OrderService $service): array
    {
        $order = $service->order;
        $agency = $order?->agency;
        $address = $agency === null ? null : $this->defaultAddressOf(MorphMap::AGENCY, $agency->id);

        return [
            'agencyCode' => $agency?->code,
            'agencyName' => $agency?->name,
            'point' => $agency?->loading_point,
            'depotCode' => $order?->depot?->code,
            'depotName' => $order?->depot?->name,
            'email' => $agency?->email,
            'phone' => $agency?->phone,
            ...$this->address($address),
        ];
    }

    /**
     * @return array<string, scalar|null>
     */
    public function delivery(OrderService $service): array
    {
        $contact = $service->contacts
            ->sortByDesc(fn ($item): bool => (bool) $item->is_primary)
            ->first();

        $name = trim(($contact?->first_name_snapshot ?? '').' '.($contact?->last_name_snapshot ?? ''));

        return [
            'contactName' => $name === '' ? null : $name,
            'contactPhone' => $contact?->mobile_snapshot ?? $contact?->phone_snapshot,
            'contactEmail' => $contact?->email_snapshot,
            ...$this->address($service->address),
        ];
    }

    /**
     * L'adresse par défaut d'une entité, ou la première rattachée.
     *
     * `is_default` d'abord : une agence peut porter son siège et un quai, et
     * c'est le quai qu'elle a marqué par défaut qui charge.
     */
    private function defaultAddressOf(string $entityType, string $entityId): ?Address
    {
        $link = EntityAddress::where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->orderByDesc('is_default')
            ->with('address')
            ->first();

        return $link?->address;
    }

    /**
     * Toujours **toutes** les clés, même vides.
     *
     * Une variable déclarée dans le modèle mais absente du contexte fait
     * échouer le rendu ; un BL dont l'adresse n'a pas de second ligne n'est pas
     * un BL cassé.
     *
     * @return array<string, scalar|null>
     */
    private function address(?Address $address): array
    {
        return [
            'addressCode' => $address?->code,
            'name' => $address?->name,
            'addressLine1' => $address?->address_line_1,
            'addressLine2' => $address?->address_line_2,
            'postalCode' => $address?->postal_code,
            'city' => $address?->city,
            'country' => $address?->country,
            'instructions' => $address?->instructions,
        ];
    }
}
