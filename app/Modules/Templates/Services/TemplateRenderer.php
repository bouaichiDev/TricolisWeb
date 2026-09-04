<?php

declare(strict_types=1);

namespace App\Modules\Templates\Services;

use App\Modules\Communications\Enums\CommunicationChannel;
use App\Modules\Templates\Exceptions\TemplateRenderingFailed;
use App\Modules\Templates\Models\Template;

/**
 * Rendu sécurisé d'un modèle — messages et documents.
 *
 * Un seul moteur pour toute la plateforme : le §0.11 interdit d'en faire vivre
 * deux, qui divergeraient au premier correctif de sécurité.
 *
 * Deux syntaxes, toutes deux fermées :
 *
 * - `{{ chemin }}` — un nom simple (`order_number`) ou pointé
 *   (`invoice.invoiceNumber`), quatre segments au plus ;
 * - `{{#liste}} … {{/liste}}` — une section répétée une fois par élément.
 *
 * Ce qui reste absent est délibéré : ni expression, ni filtre, ni condition, ni
 * section imbriquée, ni appel. Le remplacement est un `preg_replace_callback`
 * sur une table close — ni `eval`, ni Blade, ni expression exécutable.
 *
 * Dans une section, un champ de ligne s'écrit avec **son chemin complet** —
 * `{{ invoice.lines.description }}` — et non par un nom relatif. Une seule
 * liste blanche gouverne alors tout le modèle, et aucun nom ne change de sens
 * selon l'endroit où il est écrit.
 *
 * Quatre refus, tous testés :
 *
 * - un chemin employé mais **non déclaré** dans `availableVariables` ;
 * - un chemin déclaré mais **sans valeur** fournie au rendu ;
 * - une valeur **non scalaire** hors section — un tableau n'a pas de
 *   représentation textuelle évidente ;
 * - une section dont la valeur **n'est pas une liste**, ou qui en imbrique une
 *   autre.
 */
final readonly class TemplateRenderer
{
    /** Un segment de chemin : lettre initiale, puis lettres, chiffres, tirets bas. */
    private const string SEGMENT = '[a-zA-Z][a-zA-Z0-9_]{0,63}';

    private const int MAX_SEGMENTS = 4;

    /** Le premier segment est écrit à part : d'où `MAX_SEGMENTS - 1` répétitions. */
    private const string PATH = self::SEGMENT.'(?:\.'.self::SEGMENT.'){0,'.(self::MAX_SEGMENTS - 1).'}';

    private const string PLACEHOLDER_PATTERN = '/\{\{\s*('.self::PATH.')\s*\}\}/';

    private const string SECTION_PATTERN = '/\{\{#\s*('.self::PATH.')\s*\}\}(.*?)\{\{\/\s*\1\s*\}\}/s';

    /**
     * Repère les accolades ouvrantes qui ne sont ni un chemin, ni une balise de
     * section : un motif mal formé doit échouer, pas se rendre littéralement.
     */
    private const string MALFORMED_PATTERN = '/\{\{(?!\s*[#\/]?\s*'.self::PATH.'\s*\}\})/';

    public function __construct(private TemplateContext $context) {}

    /**
     * Rendu d'un message : le jeu de variables doit correspondre au modèle.
     *
     * Une valeur fournie pour une variable non déclarée est refusée. C'est un
     * garde-fou d'appelant : celui qui compose un message choisit ses variables
     * en regardant le modèle, et un nom en trop signale presque toujours une
     * faute de frappe qui aurait laissé un trou dans le texte envoyé.
     *
     * @param  array<string, mixed>  $variables
     *
     * @throws TemplateRenderingFailed
     */
    public function render(Template $template, array $variables): RenderedContent
    {
        return $this->renderWith($template, $variables, strict: true);
    }

    /**
     * Rendu d'un document depuis un contexte canonique.
     *
     * Le contexte d'une facture est le même pour tous les modèles de facture :
     * dix-neuf chemins, plus les lignes. Un modèle qui en nomme trois est
     * normal, pas fautif — c'est l'inverse de la situation ci-dessus, et
     * appliquer la même règle rendrait tout modèle de facture irrésoluble.
     *
     * @param  array<string, mixed>  $context
     *
     * @throws TemplateRenderingFailed
     */
    public function renderDocument(Template $template, array $context): RenderedContent
    {
        return $this->renderWith($template, $context, strict: false);
    }

    /**
     * @param  array<string, mixed>  $variables
     */
    private function renderWith(Template $template, array $variables, bool $strict): RenderedContent
    {
        $declared = $template->declaredVariables();
        $flat = $this->context->flatten($variables);
        $lists = $this->context->lists($variables);

        if ($strict) {
            $this->rejectUndeclared(array_keys($flat), $declared);
        }

        $channel = $template->channel;

        $subject = $template->subject_template === null
            ? null
            : $this->replace($template->subject_template, $flat, $lists, $declared, $channel);

        $body = $this->replace($template->body_template, $flat, $lists, $declared, $channel);

        return new RenderedContent($subject, $body, $flat);
    }

    /**
     * Sections d'abord, chemins ensuite — mais jamais sur le même texte.
     *
     * Une section développée est mise de côté derrière un jeton, et remise en
     * place **après** la passe sur les chemins. Sans cela, une ligne de facture
     * dont la description contient littéralement `{{ invoice.total }}` verrait
     * ce texte résolu : la donnée deviendrait du modèle, et un client pourrait
     * faire écrire à sa propre facture ce qu'il n'a pas le droit de lire.
     *
     * Le jeton emploie l'octet nul, qu'aucun modèle ne peut contenir : la
     * colonne est du texte, et un octet nul y serait refusé à l'écriture.
     *
     * @param  array<string, scalar|null>  $flat
     * @param  array<string, list<array<string, scalar|null>>>  $lists
     * @param  list<string>  $declared
     */
    private function replace(
        string $content,
        array $flat,
        array $lists,
        array $declared,
        ?CommunicationChannel $channel,
    ): string {
        if (preg_match(self::MALFORMED_PATTERN, $content) === 1) {
            throw TemplateRenderingFailed::malformedPlaceholder();
        }

        $sections = [];
        $content = $this->expandSections($content, $lists, $declared, $channel, $sections);
        $content = $this->replacePlaceholders($content, $flat, $declared, $channel);

        return $sections === [] ? $content : strtr($content, $sections);
    }

    /**
     * Développe chaque section en autant de copies que d'éléments.
     *
     * Les valeurs de l'élément sont rendues **sur place**, avant que la seconde
     * passe ne s'exécute : sans cela, un texte de ligne contenant lui-même des
     * accolades serait relu comme un modèle.
     *
     * @param  array<string, list<array<string, scalar|null>>>  $lists
     * @param  list<string>  $declared
     * @param  array<string, string>  $sections  jetons produits, renseignés ici
     */
    private function expandSections(
        string $content,
        array $lists,
        array $declared,
        ?CommunicationChannel $channel,
        array &$sections,
    ): string {
        return preg_replace_callback(
            self::SECTION_PATTERN,
            function (array $match) use ($lists, $declared, $channel, &$sections): string {
                [$path, $inner] = [$match[1], $match[2]];

                if (! in_array($path, $declared, true)) {
                    throw TemplateRenderingFailed::undeclaredVariable($path);
                }

                if (! array_key_exists($path, $lists)) {
                    throw TemplateRenderingFailed::notAList($path);
                }

                if (preg_match(self::SECTION_PATTERN, $inner) === 1) {
                    throw TemplateRenderingFailed::nestedSection($path);
                }

                $rendered = '';

                foreach ($lists[$path] as $item) {
                    $values = [];

                    foreach ($item as $field => $value) {
                        $values["{$path}.{$field}"] = $value;
                    }

                    $rendered .= $this->replacePlaceholders($inner, $values, $declared, $channel);
                }

                $token = "\0section:".count($sections)."\0";
                $sections[$token] = $rendered;

                return $token;
            },
            $content,
        ) ?? $content;
    }

    /**
     * @param  array<string, scalar|null>  $values
     * @param  list<string>  $declared
     */
    private function replacePlaceholders(
        string $content,
        array $values,
        array $declared,
        ?CommunicationChannel $channel,
    ): string {
        preg_match_all(self::PLACEHOLDER_PATTERN, $content, $matches);

        $replacements = [];

        foreach (array_unique($matches[1]) as $path) {
            if (! in_array($path, $declared, true)) {
                throw TemplateRenderingFailed::undeclaredVariable($path);
            }

            if (! array_key_exists($path, $values)) {
                throw TemplateRenderingFailed::missingValue($path);
            }

            $replacements[$path] = $this->stringify($values[$path], $channel);
        }

        return preg_replace_callback(
            self::PLACEHOLDER_PATTERN,
            static fn (array $match): string => $replacements[$match[1]],
            $content,
        ) ?? $content;
    }

    /**
     * @param  list<string>  $paths
     * @param  list<string>  $declared
     */
    private function rejectUndeclared(array $paths, array $declared): void
    {
        foreach ($paths as $path) {
            if (! in_array($path, $declared, true)) {
                throw TemplateRenderingFailed::undeclaredVariable($path);
            }
        }
    }

    /**
     * Convertit une valeur en texte, puis l'échappe selon le canal.
     *
     * Seuls l'e-mail et les documents sont interprétés comme du balisage :
     * échapper un SMS y écrirait `&amp;` à la place d'une esperluette. Un
     * modèle sans canal est un document — une facture — et s'échappe donc
     * comme du HTML.
     */
    private function stringify(string|int|float|bool|null $value, ?CommunicationChannel $channel): string
    {
        $text = match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'oui' : 'non',
            default => (string) $value,
        };

        return $channel === null || $channel->escapesHtml()
            ? htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            : $text;
    }
}
