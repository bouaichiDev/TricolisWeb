<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Demandes de correction de stock (Phase 5 du portail client).
 *
 * **Évolution assumée de la conception**, décidée le 2026-09-30 : le domaine
 * Stock n'avait aucune entité pour porter une demande en attente de
 * validation (BLOCKED_DESIGN_DECISION levée). La classe
 * `StockAdjustmentRequest` est ajoutée au diagramme de la plateforme interne.
 *
 * Une demande **ne modifie jamais le stock**. Seule son acceptation, côté
 * transporteur, crée un `StockMovement` par l'Action interne — dont
 * l'identifiant est alors conservé ici (`stock_movement_id`).
 *
 * `displayed_quantity` fige ce que le client voyait au moment de sa demande :
 * la balance peut bouger d'ici la décision, et le transporteur doit pouvoir
 * lire l'écart tel qu'il a été constaté.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_adjustment_requests', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->char('customer_id', 26);
            $table->char('stock_item_id', 26);
            $table->char('stock_location_id', 26)->nullable();
            $table->decimal('displayed_quantity', 12, 3);
            $table->decimal('requested_quantity', 12, 3);
            $table->string('reason', 255);
            $table->text('comment')->nullable();
            $table->string('status', 32);
            $table->char('requested_by', 26)->nullable();
            $table->char('reviewed_by', 26)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_comment')->nullable();
            $table->char('stock_movement_id', 26)->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['customer_id', 'status']);
            $table->index('stock_item_id');
            $table->index('created_at');

            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->restrictOnDelete();
            $table->foreign('stock_item_id')->references('id')->on('stock_items')->restrictOnDelete();
            $table->foreign('stock_location_id')->references('id')->on('stock_locations')->restrictOnDelete();
            $table->foreign('requested_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('stock_movement_id')->references('id')->on('stock_movements')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustment_requests');
    }
};
