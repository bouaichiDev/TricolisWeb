<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Preuve de livraison — donnee historique a valeur probante.
     *
     * Signature et photo sont des references vers `documents` : ni
     * `signature_path`, ni `photo_path`, ni table `signatures`, ni table
     * `delivery_photos`. Le module Documents de la Phase 1 fait deja ce travail.
     *
     * Pas d'`organization_id` : la classe n'en declare pas. Le perimetre passe
     * par la commande, comme pour `order_services`.
     */
    public function up(): void
    {
        Schema::create('proofs_of_delivery', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('order_id', 26);
            $table->char('order_service_id', 26)->nullable();
            $table->char('tour_stop_id', 26)->nullable();
            // Une preuve sans destinataire ni date ne prouve rien : les deux
            // seuls champs qui situent la remise sont obligatoires.
            $table->string('recipient_name');
            $table->char('signature_document_id', 26)->nullable();
            $table->char('photo_document_id', 26)->nullable();
            $table->text('remark')->nullable();
            $table->dateTime('delivered_at');
            $table->char('created_by', 26)->nullable();

            $table->index('order_id');
            $table->index('order_service_id');
            $table->index('tour_stop_id');
            $table->index('signature_document_id');
            $table->index('photo_document_id');
            $table->index('delivered_at');
            $table->index('created_by');

            $table->foreign('order_id')->references('id')->on('orders')->restrictOnDelete();
            $table->foreign('order_service_id')->references('id')->on('order_services')->nullOnDelete();
            $table->foreign('tour_stop_id')->references('id')->on('tour_stops')->nullOnDelete();
            // RESTRICT et non SET NULL : delier silencieusement une signature
            // viderait la preuve de sa substance sans laisser de trace.
            $table->foreign('signature_document_id')->references('id')->on('documents')->restrictOnDelete();
            $table->foreign('photo_document_id')->references('id')->on('documents')->restrictOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proofs_of_delivery');
    }
};
