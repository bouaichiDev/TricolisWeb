<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La couleur d'un statut, réglée dans le référentiel.
 *
 * Le calendrier du portail client (Phase 3) montre l'état de chaque commande
 * par sa teinte. Sans colonne, cette teinte vivait dans une table écrite en
 * dur dans le frontend — ce que la spécification refuse (§7) : la couleur
 * appartient au référentiel, que l'administrateur règle.
 *
 * Nullable, sans valeur semée : un statut sans couleur garde la teinte par
 * défaut du frontend. Aucune donnée existante n'est touchée.
 *
 * Format `#RRGGBB`, validé à l'écriture.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('statuses', function (Blueprint $table): void {
            $table->string('color', 7)->nullable()->after('icon');
        });
    }

    public function down(): void
    {
        Schema::table('statuses', function (Blueprint $table): void {
            $table->dropColumn('color');
        });
    }
};
