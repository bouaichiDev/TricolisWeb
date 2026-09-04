<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Temps de montage d'un article de catalogue.
 *
 * Certains articles se posent, d'autres se montent : un canapé modulaire coûte
 * un quart d'heure qu'un carton ne coûte pas. Le temps appartient à l'article,
 * pas à la commande — c'est une propriété du produit, connue avant qu'aucune
 * commande n'existe.
 *
 * `nullable` et non `default(0)` : « pas de montage » et « montage non
 * renseigné » ne sont pas la même chose, et le second ne doit pas se faire
 * passer pour le premier dans une somme.
 *
 * `unsignedSmallInteger` plafonne à 65 535 minutes, soit quarante-cinq jours :
 * de quoi couvrir tout montage réel sans réserver quatre octets par ligne.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_catalog_items', function (Blueprint $table): void {
            $table->unsignedSmallInteger('assembly_time_minutes')->nullable()->after('height');
        });
    }

    public function down(): void
    {
        Schema::table('customer_catalog_items', function (Blueprint $table): void {
            $table->dropColumn('assembly_time_minutes');
        });
    }
};
