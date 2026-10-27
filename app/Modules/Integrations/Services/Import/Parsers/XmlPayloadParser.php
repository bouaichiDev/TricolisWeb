<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\Import\Parsers;

use DOMDocument;
use DOMElement;
use DOMText;
use RuntimeException;

/**
 * Un XML, lu **sans jamais rien résoudre**.
 *
 * - **Aucune DTD** : un `<!DOCTYPE` ou un `<!ENTITY` est refusé avant même
 *   l'analyse. C'est la seule défense sûre contre XXE et l'expansion
 *   d'entités (« billion laughs ») : ne pas leur laisser de porte.
 * - `LIBXML_NONET` : aucun accès réseau, même par un chemin oublié.
 * - Ni `LIBXML_NOENT` ni `LIBXML_DTDLOAD` : aucune substitution.
 * - Profondeur plafonnée : un XML de commande ne s'imbrique pas sur des
 *   dizaines de niveaux.
 *
 * Conversion : un élément devient un tableau de ses enfants par nom (répétés →
 * liste), ses attributs deviennent des clés `@nom`, et un élément sans enfant
 * sa valeur texte.
 */
final readonly class XmlPayloadParser implements PayloadParser
{
    public function __construct(private int $maxDepth = 32) {}

    public function parse(string $contents): array
    {
        if (preg_match('/<!(DOCTYPE|ENTITY)/i', $contents) === 1) {
            throw new RuntimeException('Ce XML déclare une DTD ou des entités : refusé par sécurité.');
        }

        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument;

        try {
            $loaded = $contents !== '' && $document->loadXML($contents, LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_COMPACT);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (! $loaded || $document->documentElement === null) {
            throw new RuntimeException('Ce contenu n’est pas un XML bien formé.');
        }

        $root = $this->element($document->documentElement, 1);

        return is_array($root) ? $root : ['#text' => $root];
    }

    public function unwrapsRoot(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>|string|null
     */
    private function element(DOMElement $element, int $depth): array|string|null
    {
        if ($depth > $this->maxDepth) {
            throw new RuntimeException('Ce XML est imbriqué trop profondément.');
        }

        $result = [];

        foreach ($element->attributes ?? [] as $attribute) {
            $result['@'.$attribute->nodeName] = $attribute->nodeValue;
        }

        $text = '';

        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $this->append($result, $child->nodeName, $this->element($child, $depth + 1));
            } elseif ($child instanceof DOMText) {
                $text .= $child->nodeValue;
            }
        }

        $text = trim($text);

        if ($result === []) {
            return $text === '' ? null : $text;
        }

        if ($text !== '') {
            $result['#text'] = $text;
        }

        return $result;
    }

    /**
     * Un nom rencontré deux fois devient une liste.
     *
     * @param  array<string, mixed>  $result
     */
    private function append(array &$result, string $name, mixed $value): void
    {
        if (! array_key_exists($name, $result)) {
            $result[$name] = $value;

            return;
        }

        if (! is_array($result[$name]) || ! array_is_list($result[$name])) {
            $result[$name] = [$result[$name]];
        }

        $result[$name][] = $value;
    }
}
