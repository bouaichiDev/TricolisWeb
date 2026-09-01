<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services;

use App\Modules\Addresses\Models\Address;
use App\Modules\Orders\Models\Service;
use App\Shared\Database\MorphMap;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Traduit les références du fichier client en identifiants de notre base.
 *
 * C'est le chaînon qui manquait pour qu'un import aboutisse. Un fichier dit
 * `LIVRAISON` et `QUAI-NORD` ; `orders.services` exige `serviceId` et
 * `addressId`, deux ULID qu'aucun client ne connaît. Sans traduction, toute
 * commande importée serait refusée sur ces deux champs.
 *
 * La correspondance porte donc `serviceCode` et `addressCode` — des codes
 * métier — et ce service les remplace par leurs identifiants avant validation.
 *
 * **La portée est une contrainte, pas un filtre.** Un service est cherché dans
 * l'organisation ; une adresse doit être **rattachée au client** de la
 * configuration, ou à l'un de ses sites. Chercher une adresse par son seul code
 * permettrait d'importer chez un client une adresse qui appartient à un autre.
 *
 * Un code inconnu **arrête le fichier**. Le deviner — prendre la première
 * adresse venue, créer un service à la volée — produirait des commandes fausses
 * que personne ne relirait.
 */
final readonly class ImportReferenceResolver
{
    /**
     * Remplace les codes par des identifiants, dans les services d'une commande.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function resolve(array $payload, string $customerId, string $organizationId, int $order): array
    {
        if (! isset($payload['services']) || ! is_array($payload['services'])) {
            return $payload;
        }

        $errors = [];

        foreach ($payload['services'] as $index => $service) {
            if (! is_array($service)) {
                continue;
            }

            $serviceCode = $service['serviceCode'] ?? null;
            $addressCode = $service['addressCode'] ?? null;

            // Les codes ne partent jamais au serveur : ils ont fait leur office.
            unset($service['serviceCode'], $service['addressCode']);

            if (is_string($serviceCode)) {
                $id = $this->serviceId($serviceCode, $organizationId);

                if ($id === null) {
                    $errors["orders.{$order}.services.{$index}.serviceCode"] =
                        ["Aucune prestation ne porte le code « {$serviceCode} »."];
                } else {
                    $service['serviceId'] = $id;
                }
            }

            if (is_string($addressCode)) {
                $id = $this->addressId($addressCode, $customerId, $organizationId);

                if ($id === null) {
                    $errors["orders.{$order}.services.{$index}.addressCode"] =
                        ["Aucune adresse de ce client ne porte le code « {$addressCode} »."];
                } else {
                    $service['addressId'] = $id;
                }
            }

            $payload['services'][$index] = $service;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $payload;
    }

    private function serviceId(string $code, string $organizationId): ?string
    {
        return Service::query()
            ->where('organization_id', $organizationId)
            ->where('code', $code)
            ->value('id');
    }

    /**
     * Adresse rattachée au client, ou à l'un de ses sites.
     *
     * `entity_addresses` porte l'organisation et le rattachement : c'est lui
     * qui garantit qu'on ne prête pas à un client l'adresse d'un autre.
     */
    private function addressId(string $code, string $customerId, string $organizationId): ?string
    {
        $siteIds = DB::table('customer_sites')
            ->where('customer_id', $customerId)
            ->pluck('id')
            ->all();

        return Address::query()
            ->where('addresses.code', $code)
            ->whereExists(function ($query) use ($customerId, $siteIds, $organizationId): void {
                $query->select(DB::raw(1))
                    ->from('entity_addresses')
                    ->whereColumn('entity_addresses.address_id', 'addresses.id')
                    ->where('entity_addresses.organization_id', $organizationId)
                    ->where(function ($scope) use ($customerId, $siteIds): void {
                        $scope->where(function ($owned) use ($customerId): void {
                            $owned->where('entity_addresses.entity_type', MorphMap::CUSTOMER)
                                ->where('entity_addresses.entity_id', $customerId);
                        });

                        if ($siteIds !== []) {
                            $scope->orWhere(function ($site) use ($siteIds): void {
                                $site->where('entity_addresses.entity_type', MorphMap::CUSTOMER_SITE)
                                    ->whereIn('entity_addresses.entity_id', $siteIds);
                            });
                        }
                    });
            })
            ->value('addresses.id');
    }
}
