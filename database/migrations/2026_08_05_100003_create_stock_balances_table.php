<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Solde d'un article a un emplacement.
     *
     * Un seul solde par couple article + emplacement : c'est l'unicite qui rend
     * le verrouillage pessimiste possible et suffisant.
     *
     * `available_quantity` est stockee — le diagramme la declare — mais jamais
     * fournie par l'appelant : `RecalculateStockBalance` la derive de
     * `quantity - reserved_quantity` a chaque ecriture.
     *
     * Seule table de la phase a porter `updated_at` : un solde est un etat
     * courant, pas un evenement.
     */
    public function up(): void
    {
        Schema::create('stock_balances', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('stock_item_id', 26);
            $table->char('stock_location_id', 26);
            $table->decimal('quantity', 12, 3)->default(0);
            $table->decimal('reserved_quantity', 12, 3)->default(0);
            $table->decimal('available_quantity', 12, 3)->default(0);
            $table->dateTime('updated_at');

            $table->unique(['stock_item_id', 'stock_location_id']);
            $table->index('stock_location_id');

            $table->foreign('stock_item_id')->references('id')->on('stock_items')->restrictOnDelete();
            $table->foreign('stock_location_id')->references('id')->on('stock_locations')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_balances');
    }
};
