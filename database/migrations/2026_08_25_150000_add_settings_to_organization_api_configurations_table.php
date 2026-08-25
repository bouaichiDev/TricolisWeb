<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L'appel lui-même se décrit dans la table, plus dans le code.
 *
 * La première version codait le chemin de Flespi en dur —
 * `/gw/channels/{id}/messages` — et confondait deux valeurs distinctes : le
 * **canal** de l'organisme, fixe, et la **référence de la course**, variable.
 * Le canal servait donc de Planid et inversement : aucune position ne
 * remontait, et un second fournisseur aurait demandé de modifier le code.
 *
 * `settings` porte le chemin et le gabarit de requête, avec deux jetons que le
 * serveur remplace : `{reference}` et `{limit}`. Le code sait substituer ; il
 * ne sait rien de Flespi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_api_configurations', function (Blueprint $table): void {
            $table->json('settings')->nullable()->after('headers');
        });
    }

    public function down(): void
    {
        Schema::table('organization_api_configurations', function (Blueprint $table): void {
            $table->dropColumn('settings');
        });
    }
};
