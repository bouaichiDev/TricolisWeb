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
 * correspondance.
 *
 * Ce sont eux le vrai travail restant d'un futur moteur : les résoudre depuis
 * une référence métier. La prévisualisation les nomme plutôt que de les taire.
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
        'services.*.serviceId',
        'services.*.addressId',
        'services.*.contacts.*.contactId',
        'packages.*.packageTypeId',
        'packages.*.groupingTypeId',
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
            if (in_array($field, self::RESOLVED_ELSEWHERE, true)) {
                continue;
            }

            $applicable[$field] = $this->withoutForeignKeyChecks($rule);
        }

        $validator = Validator::make($payload, $applicable);

        return [
            'errors' => $validator->errors()->toArray(),
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
