<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Evenement de suivi — donnee historique.
     *
     * Ni `updated_at`, ni soft delete : le §7 pose qu'un evenement ne se
     * modifie pas. Une nouvelle occurrence produit une nouvelle ligne. Aucune
     * route `PATCH` ni `DELETE` n'existe.
     *
     * Precisions des coordonnees reprises d'`addresses` : DECIMAL(10,8) et
     * DECIMAL(11,8).
     */
    public function up(): void
    {
        Schema::create('tracking_events', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->char('order_id', 26);
            $table->char('order_service_id', 26)->nullable();
            $table->char('tour_id', 26)->nullable();
            $table->char('tour_stop_id', 26)->nullable();
            $table->string('event_type', 64);
            $table->string('status', 32);
            $table->text('description')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->dateTime('occurred_at');
            // Un evenement produit par un automate n'a pas d'auteur.
            $table->char('created_by', 26)->nullable();

            $table->index('organization_id');
            $table->index('order_id');
            $table->index('order_service_id');
            $table->index('tour_id');
            $table->index('tour_stop_id');
            $table->index('event_type');
            $table->index('status');
            $table->index('occurred_at');
            $table->index('created_by');
            // Sert la consultation chronologique d'une commande, l'usage nominal.
            $table->index(['order_id', 'occurred_at']);

            // Le suivi est historique : il ne disparait pas avec ce qu'il decrit.
            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign('order_id')->references('id')->on('orders')->restrictOnDelete();
            $table->foreign('order_service_id')->references('id')->on('order_services')->nullOnDelete();
            $table->foreign('tour_id')->references('id')->on('tours')->nullOnDelete();
            $table->foreign('tour_stop_id')->references('id')->on('tour_stops')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_events');
    }
};
