<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services;

use Illuminate\Support\Facades\Validator;

/**
 * Ce qui manque encore à une charge utile construite par une correspondance.
 *
 * Le verdict est rendu sur les règles réelles de `StoreOrderRequest` — c'est le
 * seul moyen d'être exact. Mais **les identifiants en sont retirés** :
 * `customerId`, `agencyId`, `catalogItemId` et leurs semblables sont des ULID
 * de notre base, qu'aucun fichier client ne porte. Les exiger ferait échouer
 * toute prévisualisation sur des champs qui ne relèvent pas de la
 * correspondance : ils sont fournis à l'import, ou déduits du client.
 *
 * **`serviceId` et `addressId` font exception.** Eux se résolvent depuis le
 * fichier, par `serviceCode` et `addressCode`, et l'import les exige. Une
 * prévisualisation qui les passerait sous silence annoncerait « correspondance
 * valide » sur un fichier que l'import refuserait — c'est le pire des verdicts,
 * puisqu'il donne confiance à tort.
 */
final readonly class ImportPreviewValidator
{
    /**
     * Champs que le fichier ne peut pas fournir, et que le moteur devra
     * résoudre.
     *
     * @var list<string>
     */
    private const array RESOLVED_ELSEWHERE = [
        'customerId',
        'agencyId',
        'depotId',
        'lines.*.catalogItemId',
        'services.*.contacts.*.contactId',
        'packages.*.packageTypeId',
        'packages.*.groupingTypeId',
    ];

    /**
     * Identifiants que la correspondance ne porte jamais, mais que l'import
     * **resout depuis le fichier**.
     *
     * Ils sont retires des regles — la charge utile ne les contient pas encore
     * — mais leur code d'origine est exige, sans quoi l'import echouerait apres
     * un verdict favorable.
     *
     * @var list<string>
     */
    private const array RESOLVED_FROM_FILE = [
        'services.*.serviceId',
        'services.*.addressId',
    ];

    /**
     * Ce que la correspondance doit porter pour qu'un identifiant se resolve.
     *
     * @var array<string, string>
     */
    private const array RESOLVED_FROM_CODE = [
        'serviceId' => 'serviceCode',
        'addressId' => 'addressCode',
    ];

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $rules  règles de `StoreOrderRequest`
     * @return array{errors: array<string, list<string>>, resolvedElsewhere: list<string>}
     */
    public function verdict(array $payload, array $rules): array
    {
        $applicable = [];

        foreach ($rules as $field => $rule) {
            if (
                in_array($field, self::RESOLVED_ELSEWHERE, true)
                || in_array($field, self::RESOLVED_FROM_FILE, true)
            ) {
                continue;
            }

            $applicable[$field] = $this->withoutForeignKeyChecks($rule);
        }

        $validator = Validator::make($payload, $applicable);

        return [
            'errors' => array_merge(
                $validator->errors()->toArray(),
                $this->missingCodes($payload),
            ),
            'resolvedElsewhere' => self::RESOLVED_ELSEWHERE,
        ];
    }

    /**
     * Retire ce qui interrogerait la base.
     *
     * Une prévisualisation ne crée rien et ne doit rien chercher : `exists` ou
     * `unique` la rendraient dépendante des données du moment, alors qu'elle ne
     * juge que la forme de ce que la correspondance produit.
     */
    /**
     * Les codes qui manquent pour résoudre un identifiant obligatoire.
     *
     * Le service porte déjà l'identifiant ? Rien à dire — une correspondance
     * peut très bien le fournir directement. Sinon le code est requis, et
     * l'annoncer ici évite le « valide » suivi d'un refus à l'import.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, list<string>>
     */
    private function missingCodes(array $payload): array
    {
        $services = $payload['services'] ?? null;

        if (! is_array($services)) {
            return [];
        }

        $errors = [];

        foreach ($services as $index => $service) {
            if (! is_array($service)) {
                continue;
            }

            foreach (self::RESOLVED_FROM_CODE as $identifier => $code) {
                if (isset($service[$identifier]) || isset($service[$code])) {
                    continue;
                }

                $errors["services.{$index}.{$code}"] = [
                    "Ce champ est requis pour retrouver « {$identifier} » : le fichier ne porte pas d’identifiant Tricolis.",
                ];
            }
        }

        return $errors;
    }

    private function withoutForeignKeyChecks(mixed $rule): mixed
    {
        if (! is_array($rule)) {
            return $rule;
        }

        return array_values(array_filter(
            $rule,
            static fn (mixed $constraint): bool => ! is_object($constraint)
                && ! (is_string($constraint) && (
                    str_starts_with($constraint, 'exists')
                    || str_starts_with($constraint, 'unique')
                )),
        ));
    }
}
