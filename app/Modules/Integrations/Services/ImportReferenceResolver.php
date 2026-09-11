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
 * Un fichier dit `LIVRAISON` et `QUAI-NORD` ; `orders.services` exige
 * `serviceId` et `addressId`, deux ULID qu'aucun client ne connaît. Ce service
 * les remplace avant validation.
 *
 * ## Deux parties, deux façons de les nommer
 *
 * ```
 * addressCode renseigné → un point du DONNEUR D'ORDRE, cherché par son code
 * recipient renseigné   → le CLIENT FINAL, décrit en entier et créé
 * les deux              → refus : on ne saurait pas où livrer
 * aucun                 → refus à la validation : pas de destination
 * ```
 *
 * Le donneur d'ordre est connu : son code suffit, le reste se lit en base. Le
 * client final ne l'est pas : le fichier porte son nom, son téléphone, son
 * courriel et son adresse — `ImportRecipientRules` dit lesquels.
 *
 * **La portée est une contrainte, pas un filtre.** Un code d'adresse doit
 * désigner une adresse du client de la configuration, ou de l'un de ses sites :
 * chercher par le seul code permettrait d'importer l'adresse d'un autre client.
 *
 * Un code inconnu **arrête le fichier**. Le deviner produirait des commandes
 * fausses que personne ne relirait.
 */
final readonly class ImportReferenceResolver
{
    /** Partagé avec l'essai : les deux verdicts doivent dire la même chose. */
    public const string NO_DESTINATION = 'Cette prestation n’a pas de destination : ni code d’un point du donneur d’ordre (addressCode), ni client final (recipient). Si vos colonnes du client final sont remplies, vérifiez que la correspondance porte bien le bloc « recipient ».';

    public function __construct(
        private ImportRecipientRules $rules,
        private ImportedRecipient $recipients,
    ) {}

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

            $prefix = "orders.{$order}.services.{$index}";
            $problems = $this->rules->errors($service, $prefix);
            $errors = array_merge($errors, $problems);

            $serviceCode = $service['serviceCode'] ?? null;
            $addressCode = $service['addressCode'] ?? null;
            $recipient = $service['recipient'] ?? null;

            // Les codes ne partent jamais au serveur : ils ont fait leur office.
            unset($service['serviceCode'], $service['addressCode'], $service['recipient'], $service['address']);

            if (is_string($serviceCode)) {
                $id = $this->serviceId($serviceCode, $organizationId);

                if ($id === null) {
                    $errors["{$prefix}.serviceCode"] = ["Aucune prestation ne porte le code « {$serviceCode} »."];
                } else {
                    $service['serviceId'] = $id;
                }
            }

            if ($problems !== []) {
                $payload['services'][$index] = $service;

                continue;
            }

            if (is_string($addressCode)) {
                $id = $this->addressId($addressCode, $customerId, $organizationId);

                if ($id === null) {
                    $errors["{$prefix}.addressCode"] =
                        ["Aucune adresse du donneur d’ordre ne porte le code « {$addressCode} ». Pour un client final, décrivez-le sous « recipient »."];
                } else {
                    $service['addressId'] = $id;
                }
            } elseif (is_array($recipient)) {
                $created = $this->recipients->create($recipient, $organizationId);
                $service['addressId'] = $created['addressId'];
                $service['contacts'] = [$created['contact'], ...($service['contacts'] ?? [])];
            } elseif (! isset($service['addressId'])) {
                // Sans ce refus, la validation parlerait d'un `addressId` que le
                // fichier n'a jamais eu à porter. Le cas le plus fréquent : une
                // correspondance sans bloc `recipient`, dont les colonnes du
                // client final sont alors ignorées sans bruit.
                $errors["{$prefix}.addressCode"] = [self::NO_DESTINATION];
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
