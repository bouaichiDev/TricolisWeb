<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compteur des numéros de tournée.
 *
 * Le numéro cesse d'être saisi : décision du propriétaire du projet du 27 août
 * 2026. Un opérateur qui le tape produit des doublons, des trous et des formats
 * incohérents — la contrainte d'unicité le lui refuse après coup, ce qui est le
 * plus mauvais moment pour l'apprendre.
 *
 * Une table à part, et non `order_number_sequences` : celle-ci porte le mot
 * « order » jusque dans son nom, et y ranger des tournées obligerait à lire
 * `scope` pour savoir de quoi on parle.
 *
 * **Pas de colonne `year`**, contrairement aux commandes : la suite demandée est
 * un simple entier qui avance de un, sans remise à zéro annuelle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_number_sequences', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();

            // Une seule suite par organisation : c'est elle que le verrou
            // sérialise quand deux planificateurs créent en même temps.
            $table->unique('organization_id');

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_number_sequences');
    }
};
