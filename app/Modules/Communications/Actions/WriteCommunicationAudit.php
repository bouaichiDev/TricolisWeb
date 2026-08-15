<?php

declare(strict_types=1);

namespace App\Modules\Communications\Actions;

use App\Shared\Audit\WriteModelAudit;

/**
 * Audit des quatre entités de communication.
 *
 * Deux colonnes sont expurgées, pour des raisons différentes :
 *
 * - `body` — le §39 interdit de « dupliquer inutilement body complet dans tous
 *   les audits » : un corps de message contient des données personnelles, et le
 *   journal se consulte plus largement que la communication elle-même ;
 * - `provider_response` — elle vient d'un tiers et peut contenir des identifiants
 *   techniques ; le §39 interdit d'y stocker un secret fournisseur.
 *
 * Ni l'une ni l'autre n'est perdue : elles restent sur la ligne
 * `order_communications`, accessible via l'API avec la permission de lecture.
 */
final readonly class WriteCommunicationAudit extends WriteModelAudit
{
    /**
     * @return list<string>
     */
    protected function redactedColumns(): array
    {
        return ['body', 'provider_response'];
    }
}
