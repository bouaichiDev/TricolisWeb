<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\Mapping;

/**
 * Le vocabulaire — fermé — d'une correspondance d'import.
 *
 * La convention d'origine reste entière : une chaîne est un nom de colonne, un
 * tableau un modèle de collection, un objet un objet. Les directives s'y
 * ajoutent pour ce qu'elle ne savait pas dire, **toutes préfixées par `@`** —
 * aucun champ Tricolis ne commence ainsi, il n'y a donc pas d'ambiguïté :
 *
 * ```json
 * {
 *   "@records": "Orders.Order",
 *   "externalReference": "OrderNo",
 *   "orderDate": { "@source": "Date", "@type": "date" },
 *   "lines": [{ "@each": "Items.Item", "articleCode": "Sku",
 *               "quantity": { "@source": "Qty", "@type": "decimal", "@default": "1" } }],
 *   "services": [{ "serviceCode": { "@value": "LIVRAISON" } }]
 * }
 * ```
 *
 * - `@records` (racine) : où se trouvent les enregistrements dans le document ;
 * - `@each` (modèle de collection) : la sous-collection de l'enregistrement
 *   parcourue — `^.champ` y remonte à l'enregistrement parent ;
 * - `@source` : la colonne lue ; `@value` : une constante ;
 * - `@default` : la valeur quand la source est vide ;
 * - `@type` : une conversion choisie dans `TYPES`, rien d'autre.
 *
 * Aucune directive n'exécute quoi que ce soit : pas d'expression, pas de
 * fonction, pas de classe désignée par le JSON.
 */
final class MappingDirectives
{
    public const string RECORDS = '@records';

    public const string EACH = '@each';

    public const string SOURCE = '@source';

    public const string VALUE = '@value';

    public const string DEFAULT = '@default';

    public const string TYPE = '@type';

    /** Clé qui, dans une sous-collection, désigne l'enregistrement parent. */
    public const string PARENT = '^';

    /** @var list<string> */
    public const array TYPES = ['string', 'integer', 'decimal', 'boolean', 'date', 'datetime', 'time'];

    /** @var list<string> */
    public const array LEAF_KEYS = [self::SOURCE, self::VALUE, self::DEFAULT, self::TYPE];

    public static function isDirective(int|string $key): bool
    {
        return is_string($key) && str_starts_with($key, '@');
    }

    /**
     * Un objet est une feuille s'il dit d'où vient sa valeur.
     *
     * @param  array<mixed>  $node
     */
    public static function isLeaf(array $node): bool
    {
        return array_key_exists(self::SOURCE, $node) || array_key_exists(self::VALUE, $node);
    }

    /**
     * Le chemin des enregistrements, s'il est déclaré.
     *
     * @param  array<mixed>|null  $mapping
     */
    public static function recordsPath(?array $mapping): ?string
    {
        $path = $mapping[self::RECORDS] ?? null;

        return is_string($path) && trim($path) !== '' ? trim($path) : null;
    }
}
