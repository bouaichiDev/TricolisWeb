<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le catalogue des variables tarifaires.
 *
 * **Un référentiel de plateforme, comme `statuses`.** Il n'a pas
 * d'`organization_id` : les variables sont les mêmes pour tous les organismes,
 * et c'est précisément ce qui empêche un administrateur d'organisme d'inventer
 * la sienne. Il les lit, il ne les écrit pas.
 *
 * **La source est une clé, pas un chemin.** `source_key` désigne une entrée du
 * registre déclaré en code, qui sait aller de la prestation jusqu'à la valeur.
 * Une table et une colonne libres ne suffiraient pas — il faudrait aussi le
 * chemin de relation — et ouvriraient la lecture de n'importe quelle colonne,
 * mot de passe compris. Le registre dit quelles colonnes existent ; le
 * superadmin choisit lesquelles deviennent des variables, et sous quel nom.
 *
 * `kind` sépare ce qui se calcule de ce qui filtre : on multiplie un poids, on
 * ne multiplie pas un code postal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_variables', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            // Le nom ecrit dans une formule : `{P:poids}`.
            $table->string('code', 64)->unique();
            $table->string('label', 255);
            $table->string('description', 500)->nullable();
            // `numeric` entre dans une formule ; `dimension` filtre une regle
            // ou une zone de matrice.
            $table->string('kind', 16)->default('numeric');
            $table->string('source_key', 64);
            $table->string('unit', 32)->nullable();
            $table->unsignedInteger('position')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['kind', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_variables');
    }
};
