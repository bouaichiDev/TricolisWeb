<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ligne de decompte fournisseur.
     *
     * `OrderService "1" -- "0..1" ProviderSettlementLine` : un service est
     * decompte au plus une fois. L'unicite sur `order_service_id` est
     * **independante** de celle d'`invoice_lines` : le meme service peut etre
     * facture au client et decompte au fournisseur, ce sont deux flux distincts
     * (§22).
     *
     * Ni taxe, ni statut, ni date de service : le §18 les interdit.
     */
    public function up(): void
    {
        Schema::create('provider_settlement_lines', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('settlement_id', 26);
            $table->char('order_service_id', 26)->nullable();
            $table->string('description');
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_cost', 12, 2);
            // Calcule : quantity x unit_cost, jamais fourni.
            $table->decimal('total_cost', 12, 2)->default(0);

            $table->unique('order_service_id');
            $table->index('settlement_id');

            $table->foreign('settlement_id')->references('id')->on('provider_settlements')->cascadeOnDelete();
            $table->foreign('order_service_id')->references('id')->on('order_services')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_settlement_lines');
    }
};
