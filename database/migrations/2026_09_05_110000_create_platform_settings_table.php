<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les réglages de la plateforme elle-même.
 *
 * **Une seule ligne, et la contrainte le dit.** `singleton` vaut toujours `true`
 * et porte l'unicité : sans elle, deux enregistrements concurrents en
 * laisseraient deux en base, et la lecture prendrait « le premier » —
 * c'est-à-dire celui que l'ordre SQL veut bien rendre. Un booléen constant
 * paraît étrange ; c'est le seul moyen d'exprimer « au plus une ligne » dans une
 * contrainte, et une contrainte vaut mieux qu'une convention.
 *
 * **Des colonnes nommées, pas un sac clé-valeur.** La tentation est grande —
 * « on ajoutera des réglages sans migration » — et c'est précisément ce qu'on ne
 * veut pas : un JSON libre ne dit pas ce qu'il contient, ne se contraint pas, et
 * se découvre en lisant le code qui l'écrit. Un réglage de plus est une colonne
 * de plus, et un champ de plus sur l'écran. C'est le même raisonnement que pour
 * le logo d'une organisation (`docs/backend/organization-logo.md`, §3).
 *
 * Le logo par défaut en occupe deux, comme celui d'une organisation : le chemin
 * et le type. Servir le fichier demande le second, et l'aller chercher dans le
 * fichier à chaque lecture ferait un accès disque de plus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->boolean('singleton')->default(true);
            $table->string('default_logo_path')->nullable();
            $table->string('default_logo_mime_type', 64)->nullable();
            $table->timestamps();

            $table->unique('singleton');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
