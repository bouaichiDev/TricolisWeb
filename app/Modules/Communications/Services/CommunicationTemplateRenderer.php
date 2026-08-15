<?php

declare(strict_types=1);

namespace App\Modules\Communications\Services;

use App\Modules\Communications\Enums\CommunicationChannel;
use App\Modules\Communications\Exceptions\TemplateRenderingFailed;
use App\Modules\Communications\Models\CommunicationTemplate;

/**
 * Rendu sécurisé d'un template.
 *
 * Syntaxe unique : `{{ nom }}`, espaces facultatifs. Aucune expression, aucun
 * filtre, aucune condition, aucune boucle. Le remplacement est un `strtr` sur
 * une table close — ni `eval`, ni Blade, ni `preg_replace` exécutable.
 *
 * Trois refus, tous testés :
 *
 * - une variable employée mais **non déclarée** dans `availableVariables` ;
 * - une variable déclarée mais **sans valeur** fournie au rendu ;
 * - une valeur **non scalaire** — un tableau n'a pas de représentation
 *   textuelle évidente et ouvrirait la porte à une sérialisation arbitraire.
 *
 * La notation à points (`{{ order.customer.name }}`) n'est pas reconnue par le
 * motif : elle est donc traitée comme une variable inconnue et refusée. Le §13
 * interdit « l'accès arbitraire aux propriétés des modèles » ; l'interdire au
 * niveau lexical est plus sûr que de le filtrer ensuite.
 */
final readonly class CommunicationTemplateRenderer
{
    private const string PLACEHOLDER_PATTERN = '/\{\{\s*([a-zA-Z][a-zA-Z0-9_]{0,63})\s*\}\}/';

    /**
     * Repère les motifs mal formés — accolades ouvertes contenant autre chose
     * qu'un nom simple.
     */
    private const string MALFORMED_PATTERN = '/\{\{(?!\s*[a-zA-Z][a-zA-Z0-9_]{0,63}\s*\}\})/';

    /**
     * @param  array<string, mixed>  $variables
     *
     * @throws TemplateRenderingFailed
     */
    public function render(CommunicationTemplate $template, array $variables): RenderedContent
    {
        $declared = $template->declaredVariables();
        $values = $this->normalize($variables, $declared);

        $subject = $template->subject_template === null
            ? null
            : $this->replace($template->subject_template, $values, $declared, $template->channel);

        $body = $this->replace($template->body_template, $values, $declared, $template->channel);

        return new RenderedContent($subject, $body, $values);
    }

    /**
     * @param  array<string, scalar|null>  $values
     * @param  list<string>  $declared
     */
    private function replace(string $content, array $values, array $declared, CommunicationChannel $channel): string
    {
        if (preg_match(self::MALFORMED_PATTERN, $content) === 1) {
            throw TemplateRenderingFailed::malformedPlaceholder();
        }

        preg_match_all(self::PLACEHOLDER_PATTERN, $content, $matches);

        $replacements = [];

        foreach (array_unique($matches[1]) as $name) {
            if (! in_array($name, $declared, true)) {
                throw TemplateRenderingFailed::undeclaredVariable($name);
            }

            if (! array_key_exists($name, $values)) {
                throw TemplateRenderingFailed::missingValue($name);
            }

            $replacements[$name] = $this->stringify($values[$name], $channel);
        }

        return preg_replace_callback(
            self::PLACEHOLDER_PATTERN,
            static fn (array $match): string => $replacements[$match[1]],
            $content,
        ) ?? $content;
    }

    /**
     * Retient les seules valeurs correspondant à une variable déclarée, et
     * refuse tout ce qui n'est pas scalaire.
     *
     * @param  array<string, mixed>  $variables
     * @param  list<string>  $declared
     * @return array<string, scalar|null>
     */
    private function normalize(array $variables, array $declared): array
    {
        $values = [];

        foreach ($variables as $name => $value) {
            if (! in_array($name, $declared, true)) {
                throw TemplateRenderingFailed::undeclaredVariable((string) $name);
            }

            if ($value !== null && ! is_scalar($value)) {
                throw TemplateRenderingFailed::nonScalarValue((string) $name);
            }

            $values[$name] = $value;
        }

        return $values;
    }

    /**
     * Convertit une valeur en texte, puis l'échappe selon le canal.
     *
     * Seul l'e-mail est interprété comme du balisage : échapper un SMS y
     * écrirait `&amp;` à la place d'une esperluette.
     */
    private function stringify(string|int|float|bool|null $value, CommunicationChannel $channel): string
    {
        $text = match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'oui' : 'non',
            default => (string) $value,
        };

        return $channel->escapesHtml()
            ? htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            : $text;
    }
}
