<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\Mapping;

/**
 * Les champs Tricolis qu'une correspondance d'import peut alimenter.
 *
 * **La liste est tenue par le backend** : l'écran de correspondance la lit
 * ici, au lieu de laisser saisir n'importe quelle propriété. Elle est relevée
 * sur `StoreOrderRequest` et sur `ImportReferenceResolver` — les codes
 * (`serviceCode`, `addressCode`, `recipient`) remplacent les ULID qu'aucun
 * fichier client ne porte.
 *
 * Les identifiants (`customerId`, `agencyId`, `source`…) sont absents : c'est
 * la configuration et le moteur qui les imposent, jamais le fichier.
 */
final class ImportTargetFields
{
    /**
     * [chemin, exigence, contrainte] ; exigence : required | optional | conditional.
     *
     * @var array<string, list<array{0: string, 1: string, 2?: string}>>
     */
    private const array ORDER = [
        'order' => [
            ['orderDate', 'required', 'date'],
            ['externalReference', 'conditional', 'clé d’idempotence, max 255'],
            ['customerReference', 'optional', 'max 255'],
            ['orderType', 'optional', 'max 64'],
            ['groupCode', 'optional', 'max 255'],
            ['workerRemark', 'optional'],
        ],
        'lines' => [
            ['lines[].quantity', 'required', '> 0'],
            ['lines[].name', 'conditional', 'obligatoire sans article de catalogue'],
            ['lines[].articleCode', 'optional', 'max 255'],
            ['lines[].barcode', 'optional', 'max 255'],
            ['lines[].externalReference', 'optional', 'max 255'],
            ['lines[].description', 'optional'],
            ['lines[].weight', 'optional', '≥ 0'],
            ['lines[].volume', 'optional', '≥ 0'],
            ['lines[].length', 'optional', '≥ 0'],
            ['lines[].width', 'optional', '≥ 0'],
            ['lines[].height', 'optional', '≥ 0'],
        ],
        'packages' => [
            ['packages[].key', 'optional', 'clé locale au fichier, max 64'],
            ['packages[].parentKey', 'optional', 'colis contenant, déclaré avant'],
            ['packages[].reference', 'optional', 'max 255'],
            ['packages[].barcode', 'optional', 'max 128'],
            ['packages[].description', 'optional', 'max 255'],
            ['packages[].quantity', 'optional', '> 0'],
            ['packages[].weight', 'optional', '≥ 0'],
            ['packages[].volume', 'optional', '≥ 0'],
            ['packages[].lines[].lineKey', 'optional', 'rang de la ligne, à partir de 0'],
            ['packages[].lines[].quantity', 'optional', '> 0'],
        ],
    ];

    /** @var list<string> */
    public const array REQUIREMENTS = ['required', 'optional', 'conditional'];

    /**
     * @return list<array{key: string, fields: list<array{path: string, requirement: string, constraint: string|null}>}>
     */
    public static function groups(): array
    {
        $groups = [];

        foreach ([...self::ORDER, ...ImportServiceTargetFields::GROUPS] as $key => $fields) {
            $groups[] = [
                'key' => $key,
                'fields' => array_map(static fn (array $field): array => [
                    'path' => $field[0],
                    'requirement' => $field[1],
                    'constraint' => $field[2] ?? null,
                ], $fields),
            ];
        }

        return $groups;
    }

    /**
     * Tous les chemins cibles, pour contrôler une correspondance.
     *
     * @return list<string>
     */
    public static function paths(): array
    {
        $paths = [];

        foreach (self::groups() as $group) {
            foreach ($group['fields'] as $field) {
                $paths[] = $field['path'];
            }
        }

        return $paths;
    }
}
