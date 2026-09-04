<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ce qu'un rôle voit sur son tableau de bord.
 *
 * **Une ligne par rôle, jamais par utilisateur.** Un tableau de bord réglé par
 * compte serait un tableau de bord que personne n'administre : il faudrait le
 * refaire à chaque arrivée, et un métier de douze personnes en aurait douze
 * versions divergentes. Le rôle porte déjà les permissions et le menu ; il
 * porte aussi ce que ce métier regarde.
 *
 * **Une colonne JSON, et non une table pivot.** La différence tient à un cas :
 * un rôle qui ne veut voir **aucun** widget. Une pivot ne sait pas l'écrire —
 * zéro ligne y signifie déjà « rien de configuré », c'est-à-dire « les défauts
 * du catalogue », soit exactement le contraire. Avec une configuration dédiée,
 * l'absence de ligne et `{"widgets": []}` disent deux choses différentes, et
 * `RoleDashboardWidgets` les distingue.
 *
 * Ce que le JSON contient est **fermé** : une clé du catalogue, un rang. Ni
 * SQL, ni nom de classe, ni nom de composant React, ni URL — la validation
 * refuse tout le reste, et le catalogue reste en code (`DashboardWidgetRegistry`).
 *
 * Pas d'`organization_id` : le rôle en porte déjà un, et le dupliquer ici
 * créerait deux vérités sur la même appartenance. Pas de `status` non plus —
 * une configuration n'a pas de cycle de vie, elle existe ou elle n'existe pas,
 * et la réinitialiser est une suppression.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_dashboard_configurations', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('role_id', 26);
            $table->json('widgets');
            $table->timestamps();

            // Une seule configuration par rôle. Sans cette contrainte, deux
            // enregistrements concurrents en laisseraient deux en base, et la
            // lecture prendrait « la première » — c'est-à-dire celle que
            // l'ordre SQL veut bien rendre.
            $table->unique('role_id');
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_dashboard_configurations');
    }
};
