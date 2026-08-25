<?php

declare(strict_types=1);

namespace App\Modules\Orders\Services;

use App\Modules\Addresses\Models\Address;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\Depot;
use App\Modules\Catalogs\Models\CustomerCatalogItem;
use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Models\Service;
use App\Modules\Types\Models\TypeItem;
use Illuminate\Validation\ValidationException;

/**
 * Vérifie que chaque référence d'une commande appartient au bon périmètre.
 *
 * Toutes les clés étrangères d'une commande sont contrôlées ici, en un seul
 * endroit : c'est la garantie qu'aucun contrôleur n'oublie un contrôle.
 * Les messages portent le chemin exact du champ fautif, comme le demande la
 * création complète.
 */
final readonly class OrderScopeGuard
{
    public function customer(string $customerId, string $organizationId, string $field = 'customerId'): Customer
    {
        return $this->findOrFail(
            Customer::where('organization_id', $organizationId)->whereKey($customerId)->first(),
            $field,
            'Ce client n’appartient pas à l’organisation active.',
        );
    }

    public function agency(string $agencyId, string $organizationId, string $field = 'agencyId'): Agency
    {
        return $this->findOrFail(
            Agency::where('organization_id', $organizationId)->whereKey($agencyId)->first(),
            $field,
            'Cette agence n’appartient pas à l’organisation active.',
        );
    }

    public function depot(string $depotId, string $organizationId, string $field = 'depotId'): Depot
    {
        return $this->findOrFail(
            Depot::whereKey($depotId)->whereHas('agency', fn ($query) => $query->where('organization_id', $organizationId))->first(),
            $field,
            'Ce dépôt n’appartient pas à l’organisation active.',
        );
    }

    /**
     * Une adresse est utilisable si une liaison la rattache à l'organisation.
     */
    public function address(string $addressId, string $organizationId, string $field = 'addressId'): Address
    {
        return $this->findOrFail(
            Address::whereKey($addressId)->whereHas('entityAddresses', fn ($query) => $query->where('organization_id', $organizationId))->first(),
            $field,
            'Cette adresse n’est pas accessible dans l’organisation active.',
        );
    }

    public function service(string $serviceId, string $organizationId, string $field = 'serviceId'): Service
    {
        return $this->findOrFail(
            Service::where('organization_id', $organizationId)->whereKey($serviceId)->first(),
            $field,
            'Ce service n’appartient pas à l’organisation active.',
        );
    }

    public function packageType(string $packageTypeId, string $organizationId, string $field = 'packageTypeId'): TypeItem
    {
        return $this->typeItem($packageTypeId, $organizationId, 'package', $field,
            'Ce type de colis n’appartient pas à l’organisation active.');
    }

    public function groupingType(string $groupingTypeId, string $organizationId, string $field = 'groupingTypeId'): TypeItem
    {
        return $this->typeItem($groupingTypeId, $organizationId, 'grouping', $field,
            'Ce type de regroupement n’appartient pas à l’organisation active.');
    }

    /**
     * Une valeur de référentiel, de la source attendue.
     *
     * Le code de la source est vérifié en plus de l'organisation : depuis que
     * les référentiels partagent une table, « Palette » et « Camion 19T » y
     * cohabitent, et seul ce filtre empêche d'affecter l'un pour l'autre.
     */
    private function typeItem(string $id, string $organizationId, string $typeCode, string $field, string $message): TypeItem
    {
        return $this->findOrFail(
            TypeItem::where('organization_id', $organizationId)
                ->whereKey($id)
                ->whereHas('type', fn ($query) => $query->where('code', $typeCode))
                ->first(),
            $field,
            $message,
        );
    }

    /**
     * L'article doit appartenir à un catalogue actif du client de la commande.
     */
    public function catalogItem(string $itemId, Customer $customer, string $field = 'catalogItemId'): CustomerCatalogItem
    {
        $item = CustomerCatalogItem::whereKey($itemId)
            ->whereHas('catalog', fn ($query) => $query->where('customer_id', $customer->id)->where('status', 'active'))
            ->where('status', 'active')
            ->first();

        return $this->findOrFail(
            $item,
            $field,
            'Cet article n’appartient pas à un catalogue actif de ce client.',
        );
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  TModel|null  $model
     * @return TModel
     */
    private function findOrFail(mixed $model, string $field, string $message): mixed
    {
        if ($model === null) {
            throw ValidationException::withMessages([$field => [$message]]);
        }

        return $model;
    }
}
