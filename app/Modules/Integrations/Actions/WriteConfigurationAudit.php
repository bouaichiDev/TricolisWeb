<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Actions;

use App\Shared\Audit\WriteModelAudit;

/**
 * Écriture et audit communs aux configurations d'intégration.
 *
 * Les trois configurations — import, API, export — suivent exactement le même
 * cycle : créer ou modifier, comparer, journaliser les seuls champs changés.
 * Trois Actions identiques à un nom de table près n'auraient rien apporté.
 *
 * Le mécanisme est porté par `WriteModelAudit`, partagé avec les communications
 * depuis la Phase 9. Seule la liste des colonnes sensibles reste ici : ni
 * empreinte de clé, ni mot de passe chiffré n'ont à figurer dans un audit, qui
 * se consulte plus largement que la table elle-même.
 */
final readonly class WriteConfigurationAudit extends WriteModelAudit
{
    /**
     * @return list<string>
     */
    protected function redactedColumns(): array
    {
        return ['api_key_hash', 'encrypted_password'];
    }
}
