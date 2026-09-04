<?php

declare(strict_types=1);

namespace App\Modules\Templates\Services;

/**
 * Résultat d'un rendu : un objet et un corps.
 *
 * Le modèle n'est jamais modifié par le rendu (§13) ; ce couple est ce qui
 * sera figé dans `OrderCommunication.subject` et `OrderCommunication.body`,
 * ou dans `Invoice.renderedBody` à la clôture.
 */
final readonly class RenderedContent
{
    /**
     * @param  array<string, scalar|null>  $variables  valeurs réellement employées
     */
    public function __construct(
        public ?string $subject,
        public string $body,
        public array $variables,
    ) {}
}
