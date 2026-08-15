<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reservation de stock pour une ligne de commande.
     *
     * Une reservation liberee n'est pas supprimee : `released_at` est
     * renseigne, la ligne reste. Le §23 l'exige, et c'est ce qui permet de
     * retracer ce qui a ete immobilise puis relache.
     */
    public function up(): void
    {
        Schema::create('stock_reservations', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('stock_item_id', 26);
            $table->char('stock_location_id', 26);
            $table->char('order_line_id', 26);
            $table->decimal('quantity', 12, 3);
            $table->string('status', 32);
            $table->dateTime('reserved_at');
            $table->dateTime('released_at')->nullable();

            $table->index('stock_item_id');
            $table->index('stock_location_id');
            $table->index('order_line_id');
            $table->index('status');
            $table->index('reserved_at');
            $table->index('released_at');

            $table->foreign('stock_item_id')->references('id')->on('stock_items')->restrictOnDelete();
            $table->foreign('stock_location_id')->references('id')->on('stock_locations')->restrictOnDelete();
            $table->foreign('order_line_id')->references('id')->on('order_lines')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_reservations');
    }
};
