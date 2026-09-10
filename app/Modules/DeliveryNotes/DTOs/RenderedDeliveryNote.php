<?php

declare(strict_types=1);

namespace App\Modules\DeliveryNotes\DTOs;

use App\Modules\Templates\Models\Template;

/**
 * Le document d'un bon de livraison, et d'où il vient.
 *
 * La provenance accompagne le document parce que sans elle l'utilisateur ne
 * saurait pas *pourquoi* il voit cette mise en page : son modèle client
 * a-t-il servi, ou n'a-t-il jamais été créé ?
 *
 * **Aucun repli.** Contrairement à la facture, un BL sans modèle ne se rend
 * pas : il n'existe pas de mise en page livrée pour lui, et en inventer une
 * ferait remettre au client un document que personne n'a validé. L'appelant
 * reçoit une erreur qui nomme le manque, et l'écran renvoie vers « Modèles ».
 */
final readonly class RenderedDeliveryNote
{
    public function __construct(
        public string $html,
        public string $number,
        public string $templateId,
        public string $templateCode,
        public string $templateName,
        /** `customer` ou `global` — la portée du modèle employé. */
        public string $scope,
    ) {}

    public static function fromTemplate(string $html, string $number, Template $template): self
    {
        return new self(
            html: $html,
            number: $number,
            templateId: $template->id,
            templateCode: $template->code,
            templateName: $template->name,
            scope: $template->scope(),
        );
    }
}
