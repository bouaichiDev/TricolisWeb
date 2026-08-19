<?php

use App\Modules\Orders\Enums\OrderStatus;
use App\Shared\Database\MorphMap;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reporte le comportement des statuts sur les lignes déjà semées.
 *
 * `allows_content_changes` et `requires_reason` ont été ajoutées avec une valeur
 * par défaut à `false`. Les statuts semés **avant** cet ajout les ont donc
 * reçues à `false`, et le seeder — qui ne réécrit jamais une ligne existante —
 * ne pouvait pas les corriger.
 *
 * Conséquence sur une base déjà en service : plus aucune commande n'était
 * modifiable, quel que soit son statut, et l'annulation n'exigeait plus de
 * motif. La correction est une migration, pas un semis : elle est liée au
 * changement de schéma et ne doit s'appliquer qu'une fois.
 *
 * Elle ne touche que les statuts de commande — les seuls dont le comportement
 * soit défini — et ne peut écraser aucun réglage : les colonnes venaient
 * d'apparaître, personne n'avait encore pu les régler.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (OrderStatus::cases() as $case) {
            DB::table('statuses')
                ->where('source', MorphMap::ORDER)
                ->where('code', $case->value)
                ->update([
                    'allows_content_changes' => $case->allowsContentChanges(),
                    'requires_reason' => $case->requiresReason(),
                ]);
        }
    }

    /**
     * Rien à défaire : le retour arrière est la valeur par défaut des colonnes,
     * que la migration précédente supprime de toute façon.
     */
    public function down(): void {}
};
