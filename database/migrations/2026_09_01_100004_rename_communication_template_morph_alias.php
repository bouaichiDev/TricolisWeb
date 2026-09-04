<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * L'alias polymorphe suit le modèle.
 *
 * `audit_logs.entity_type` stocke l'alias, pas la classe. Renommer le modèle
 * sans toucher aux lignes déjà écrites laisserait un journal pointant vers
 * `communication_template`, un alias que plus rien ne déclare : l'écran d'audit
 * afficherait des entrées qu'il ne sait plus relier à leur modèle.
 *
 * Une mise à jour de chaîne, sur une colonne indexée. Aucune ligne n'est perdue.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('audit_logs')
            ->where('entity_type', 'communication_template')
            ->update(['entity_type' => 'template']);
    }

    public function down(): void
    {
        DB::table('audit_logs')
            ->where('entity_type', 'template')
            ->update(['entity_type' => 'communication_template']);
    }
};
