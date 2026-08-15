<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La colonne `catalog_item_id` existait depuis la Phase 1, mais sans
     * contrainte : `customer_catalog_items` n'était pas encore créée.
     *
     * `RESTRICT` protège l'historique : un article encore référencé par une
     * commande ne peut pas être supprimé du catalogue. Les lignes conservent de
     * toute façon une copie des données de l'article, la référence ne sert qu'à
     * remonter à la source.
     */
    public function up(): void
    {
        Schema::table('order_lines', function (Blueprint $table): void {
            $table->index('catalog_item_id');
            $table->foreign('catalog_item_id')->references('id')->on('customer_catalog_items')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_lines', function (Blueprint $table): void {
            $table->dropForeign(['catalog_item_id']);
            $table->dropIndex(['catalog_item_id']);
        });
    }
};
