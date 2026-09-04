<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Référentiel des statuts, commun à toute la plateforme.
 *
 * **Portée plateforme, pas organisation.** Un statut décrit le cycle de vie du
 * domaine lui-même : deux organismes qui nommeraient différemment l'état
 * « confirmée » rendraient les échanges et les exports incomparables. La table
 * ne porte donc pas d'`organization_id`, et seule la plateforme l'écrit.
 *
 * **Ce qui relie ce référentiel au reste :** `code`. C'est cette valeur qui est
 * stockée dans les colonnes `status` existantes — `orders.status`,
 * `packages.status`, `order_services.status`. Ces colonnes restent des chaînes,
 * sans clé étrangère : elles portent déjà des données, elles appartiennent à des
 * tables différentes, et une même valeur — « draft » — existe pour plusieurs
 * entités. C'est `source` qui lève l'ambiguïté.
 *
 * **`source`** porte un alias de `MorphMap` — `order`, `package`,
 * `order_service`… — le vocabulaire déjà utilisé partout ailleurs pour désigner
 * une entité. En inventer un second en produirait deux à maintenir.
 *
 * **`status`** est l'identifiant numérique du statut, repris des systèmes qui
 * l'attendent sous cette forme. Il est unique par source, comme le code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statuses', function (Blueprint $table): void {
            $table->char('id', 26)->primary();

            // Entité concernée : alias de la morph map.
            $table->string('source', 64);
            // Identifiant numérique du statut, unique dans sa source.
            $table->unsignedInteger('status');
            // Valeur réellement stockée dans les colonnes `status` du domaine.
            $table->string('code', 64);
            $table->string('label');
            // Nom d'icône Lucide, celui qu'affiche déjà le frontend.
            $table->string('icon', 64)->nullable();
            $table->boolean('active')->default(true);
            // Ce statut déclenche-t-il un envoi au client ?
            $table->boolean('is_to_send')->default(false);
            // Ordre d'affichage dans les écrans d'administration.
            $table->unsignedSmallInteger('position')->nullable();
            $table->timestamps();

            $table->unique(['source', 'code']);
            $table->unique(['source', 'status']);
            $table->index(['source', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statuses');
    }
};
