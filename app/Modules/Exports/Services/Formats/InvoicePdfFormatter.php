<?php

declare(strict_types=1);

namespace App\Modules\Exports\Services\Formats;

use App\Modules\Billing\DTOs\RenderedInvoice;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * La facture en PDF, telle qu'un humain la lit.
 *
 * **Le mapping de champs ne s'y applique pas, et c'est voulu.** Renommer
 * `invoiceNumber` en `numero` sert un automate qui cherche une clé ; un PDF n'a
 * pas de clés, il a des libellés en français devant des valeurs.
 *
 * **L'encodage déclaré est ignoré** : un PDF embarque ses polices et sa propre
 * table de caractères. Prétendre le produire en ISO-8859-1 serait mentir sur le
 * fichier livré.
 *
 * **La mise en page ne vit plus ici depuis la Phase 9.** Elle vient du modèle
 * `INVOICE` résolu — celui du client, sinon celui de l'organisation — et le
 * §0.26 impose que le PDF en soit le rendu. Cette classe ne décide plus de quoi
 * la facture a l'air : elle met une page HTML sur du papier A4.
 *
 * Une facture close sert le document figé à sa clôture ; retoucher le modèle
 * ensuite ne réécrit pas ce qui a déjà été facturé.
 *
 * Elle n'implémente plus `InvoiceFormatter` : ce contrat transpose un DTO, et
 * un PDF ne se produit pas à partir d'un DTO seul. Le déclarer et lever une
 * exception aurait laissé croire à l'appelant qu'il pouvait l'employer.
 */
final readonly class InvoicePdfFormatter
{
    private const string PAPER = 'a4';

    /**
     * Le document déjà rendu, mis sur papier.
     *
     * @param  array<string, mixed>  $settings
     */
    public function fromDocument(RenderedInvoice $document, array $settings): string
    {
        $statics = is_array($settings['staticValues'] ?? null) ? $settings['staticValues'] : [];

        return Pdf::loadHTML($this->withFootnotes($document->html, $this->footnotes($statics)))
            ->setPaper(self::PAPER)
            ->output();
    }

    public function extension(): string
    {
        return 'pdf';
    }

    public function contentType(): string
    {
        return 'application/pdf';
    }

    /**
     * Les valeurs fixes du client, rendues en pied de document.
     *
     * Seuls les scalaires passent : un tableau imbriqué n'a pas de place dans
     * une ligne de bas de page, et l'y aplatir donnerait une bouillie.
     *
     * @param  array<string, mixed>  $statics
     * @return array<string, string>
     */
    private function footnotes(array $statics): array
    {
        $notes = [];

        foreach ($statics as $key => $value) {
            if (is_string($key) && is_scalar($value)) {
                $notes[$key] = (string) $value;
            }
        }

        return $notes;
    }

    /**
     * Ajoute les valeurs fixes du client en pied de document.
     *
     * Ce que le §66 autorise de configurer — les valeurs fixes — trouve sa
     * place ici, là où les clients demandent leur code fournisseur ou une
     * mention légale. Le reste du document appartient au modèle.
     *
     * @param  array<string, string>  $notes
     */
    private function withFootnotes(string $html, array $notes): string
    {
        if ($notes === []) {
            return $html;
        }

        $lines = '';

        foreach ($notes as $key => $value) {
            $lines .= '<div>'.e($key).' : '.e($value).'</div>';
        }

        return $html.'<div style="margin-top:24px;font-size:9px;color:#666">'.$lines.'</div>';
    }
}
