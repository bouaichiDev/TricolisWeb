<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agence qui reçoit par défaut les commandes d'un client.
 *
 * Une commande exige une agence ; une commande passée depuis le portail
 * client n'a personne pour la choisir. Le transporteur la règle sur la fiche
 * du client. Vide, c'est la première agence active de l'organisation qui
 * s'applique (`CustomerDefaultAgency`).
 *
 * Nullable et `nullOnDelete` : supprimer une agence ne doit pas bloquer, ni
 * supprimer, les clients qui la désignaient.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->char('default_agency_id', 26)->nullable()->after('organization_id');
            $table->foreign('default_agency_id')->references('id')->on('agencies')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['default_agency_id']);
            $table->dropColumn('default_agency_id');
        });
    }
};
