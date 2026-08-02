<?php

declare(strict_types=1);

namespace App\Modules\Communications\Services;

/**
 * Résultat d'un rendu de template : un objet et un corps.
 *
 * Le template n'est jamais modifié par le rendu (§13) ; ce couple est ce qui
 * sera figé dans `OrderCommunication.subject` et `OrderCommunication.body`.
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
