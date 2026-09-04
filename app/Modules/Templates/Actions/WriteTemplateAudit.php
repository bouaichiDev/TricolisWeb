<?php

declare(strict_types=1);

namespace App\Modules\Templates\Actions;

use App\Shared\Audit\WriteModelAudit;

/**
 * Audit des modèles.
 *
 * `body_template` et `subject_template` sont expurgés. Ce n'est pas un secret
 * qu'on protège, c'est le journal : une mise en page de facture pèse plusieurs
 * kilo-octets de HTML, et deux copies — avant et après — de chaque retouche
 * rendraient l'audit illisible et coûteux à parcourir.
 *
 * Rien n'est perdu : le corps courant reste sur la ligne `templates`, et le
 * corps réellement envoyé est figé dans la communication ou la facture.
 */
final readonly class WriteTemplateAudit extends WriteModelAudit
{
    /**
     * @return list<string>
     */
    protected function redactedColumns(): array
    {
        return ['body_template', 'subject_template'];
    }
}
