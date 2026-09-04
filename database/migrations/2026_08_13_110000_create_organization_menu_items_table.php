<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Réglages de menu propres à une organisation.
 *
 * Une ligne par entrée **personnalisée**, pas par entrée du catalogue :
 * l'absence de ligne signifie « valeurs par défaut du catalogue ». Une
 * organisation qui ne touche à rien n'a donc aucune ligne, et une entrée
 * ajoutée au catalogue apparaît d'elle-même partout.
 *
 * Le catalogue lui-même reste en code (`App\Shared\Menu\MenuCatalogue`) : route,
 * icône et clé i18n y sont couplées au frontend, et les stocker en base
 * permettrait de saisir une route qui n'existe pas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_menu_items', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->string('code', 64);
            $table->boolean('is_visible')->default(true);
            $table->unsignedSmallInteger('position')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_menu_items');
    }
};
