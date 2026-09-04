<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les référentiels de type, réunis en deux tables.
 *
 * `package_types`, `grouping_types` et `vehicle_types` avaient la même forme —
 * code, nom, statut, portée organisationnelle — et chaque nouveau référentiel
 * demandait une table, un modèle, un contrôleur et une migration. `types` porte
 * désormais la source (véhicule, colis, groupage…) et `type_items` ses valeurs,
 * de sorte qu'un organisme ajoute un référentiel sans toucher au schéma.
 *
 * `is_system` distingue les sources auxquelles une colonne se réfère —
 * `vehicles.vehicle_type_id`, `packages.package_type_id` — de celles qu'un
 * organisme crée librement. Supprimer les premières laisserait des références
 * pendantes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('types', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->string('code', 64);
            $table->string('name');
            $table->string('status', 32)->default('active');
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'status']);

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });

        Schema::create('type_items', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->char('type_id', 26);
            $table->string('code', 64);
            $table->string('name');
            $table->string('status', 32)->default('active');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            // Le code n'est unique qu'au sein d'une source : « STD » peut
            // designer un colis standard et un vehicule standard.
            $table->unique(['organization_id', 'type_id', 'code']);
            $table->index(['organization_id', 'status']);

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('type_id')->references('id')->on('types')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('type_items');
        Schema::dropIfExists('types');
    }
};
