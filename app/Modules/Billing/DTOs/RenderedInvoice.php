<?php

declare(strict_types=1);

namespace App\Modules\Billing\DTOs;

use App\Modules\Templates\Models\Template;

/**
 * Le document d'une facture, et d'où il vient.
 *
 * La provenance n'est pas décorative : l'aperçu doit dire à l'utilisateur quel
 * modèle a servi et à quelle portée — sans quoi, voyant sa mise en page globale
 * s'afficher, il ne saurait pas si son modèle client a été ignoré ou s'il n'a
 * simplement jamais été créé.
 *
 * `frozen` marque le document figé d'une facture close : ni le modèle courant,
 * ni sa portée actuelle n'ont plus de sens pour elle.
 */
final readonly class RenderedInvoice
{
    private function __construct(
        public string $html,
        public ?string $templateId,
        public ?string $templateName,
        /** `customer`, `global` ou `fallback`. */
        public string $scope,
        public bool $isFrozen,
    ) {}

    public static function fromTemplate(string $html, Template $template): self
    {
        return new self($html, $template->id, $template->name, $template->scope(), false);
    }

    public static function fallback(string $html): self
    {
        return new self($html, null, null, 'fallback', false);
    }

    public static function frozen(string $html, ?string $templateId): self
    {
        return new self($html, $templateId, null, 'frozen', true);
    }
}
