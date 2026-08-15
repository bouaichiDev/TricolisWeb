<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mouvement de stock — donnee historique.
     *
     * Ni `updated_at`, ni route `PATCH`, ni route `DELETE` : le §17 pose qu'un
     * mouvement ne se modifie pas. Une correction est un nouveau mouvement.
     *
     * Les deux emplacements sont nullables : une entree n'a pas de source, une
     * sortie pas de destination. L'application exige qu'au moins l'un des deux
     * soit fourni (§21).
     *
     * `source_entity_type` porte un alias de la morph map, jamais un nom de
     * classe PHP. **Aucune cle etrangere sur `source_entity_id`** : le §18
     * l'interdit, et la colonne peut designer plusieurs tables.
     */
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('stock_item_id', 26);
            $table->char('source_location_id', 26)->nullable();
            $table->char('destination_location_id', 26)->nullable();
            $table->string('movement_type', 64);
            $table->decimal('quantity', 12, 3);
            $table->string('source_entity_type', 64)->nullable();
            $table->char('source_entity_id', 26)->nullable();
            $table->char('created_by', 26)->nullable();
            $table->dateTime('created_at');

            $table->index('stock_item_id');
            $table->index('source_location_id');
            $table->index('destination_location_id');
            $table->index('movement_type');
            $table->index(['source_entity_type', 'source_entity_id']);
            $table->index('created_by');
            $table->index('created_at');
            // Sert la consultation chronologique d'un article, l'usage nominal.
            $table->index(['stock_item_id', 'created_at']);

            $table->foreign('stock_item_id')->references('id')->on('stock_items')->restrictOnDelete();
            $table->foreign('source_location_id')->references('id')->on('stock_locations')->restrictOnDelete();
            $table->foreign('destination_location_id')->references('id')->on('stock_locations')->restrictOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
