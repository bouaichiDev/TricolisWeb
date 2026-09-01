<?php

declare(strict_types=1);

namespace App\Modules\Templates\Services;

use App\Modules\Templates\Exceptions\TemplateRenderingFailed;

/**
 * Met un contexte de rendu à plat, sans jamais l'ouvrir plus loin qu'il ne faut.
 *
 * Le moteur ne connaît que des chemins — `invoice.total`, `customer.name` — et
 * des listes. Cette classe est le seul endroit qui transforme un tableau
 * imbriqué en ces deux formes, ce qui donne un point unique où contrôler la
 * profondeur et le type des valeurs.
 *
 * **Ce qui entre ici est déjà un DTO.** Le §0.12 interdit de donner un modèle
 * Eloquent au moteur : `invoice.lines` vient d'`InvoiceExportData`, pas d'une
 * relation vivante. Aucune propriété n'est lue par réflexion, aucun accesseur
 * n'est appelé.
 *
 * Les listes s'arrêtent à une profondeur : une facture a des lignes, pas des
 * lignes de lignes. Une liste imbriquée est refusée plutôt qu'aplatie en
 * silence.
 */
final readonly class TemplateContext
{
    private const int MAX_DEPTH = 4;

    /**
     * Chemins scalaires du contexte, listes exclues.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, scalar|null>
     */
    public function flatten(array $context, string $prefix = '', int $depth = 1): array
    {
        $flat = [];

        foreach ($context as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if (is_array($value)) {
                if (array_is_list($value)) {
                    continue;
                }

                if ($depth >= self::MAX_DEPTH) {
                    throw TemplateRenderingFailed::tooDeep($path);
                }

                $flat = [...$flat, ...$this->flatten($value, $path, $depth + 1)];

                continue;
            }

            if ($value !== null && ! is_scalar($value)) {
                throw TemplateRenderingFailed::nonScalarValue($path);
            }

            $flat[$path] = $value;
        }

        return $flat;
    }

    /**
     * Listes du contexte, chacune réduite aux champs scalaires de ses éléments.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, list<array<string, scalar|null>>>
     */
    public function lists(array $context, string $prefix = '', int $depth = 1): array
    {
        $lists = [];

        foreach ($context as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if (! is_array($value)) {
                continue;
            }

            if (array_is_list($value)) {
                $lists[$path] = array_map(
                    fn (mixed $item): array => $this->row($item, $path),
                    $value,
                );

                continue;
            }

            if ($depth < self::MAX_DEPTH) {
                $lists = [...$lists, ...$this->lists($value, $path, $depth + 1)];
            }
        }

        return $lists;
    }

    /**
     * Un élément de liste, mis à plat.
     *
     * Une ligne de facture porte son adresse de livraison : la conserver permet
     * d'écrire `{{ invoice.lines.address.city }}` dans le tableau des lignes.
     * Une liste **dans** une ligne est en revanche ignorée — la section ne se
     * développe qu'une fois.
     *
     * @return array<string, scalar|null>
     */
    private function row(mixed $item, string $path): array
    {
        if (! is_array($item)) {
            throw TemplateRenderingFailed::notAList($path);
        }

        return $this->flatten(
            array_filter($item, static fn (mixed $value): bool => ! is_array($value) || ! array_is_list($value)),
            '',
            2,
        );
    }
}
