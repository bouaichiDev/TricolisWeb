<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reclamation client.
     *
     * `created_at` existe parce que le diagramme le declare ; `updated_at` non,
     * pour la meme raison. Aucun `legacy_id` : le §14 du prompt en mentionne un,
     * le diagramme n'en contient pas — et le §1 donne priorite au diagramme.
     *
     * Les champs de resolution (`decision`, `follow_up`, `result`, `cost`,
     * `responsible_user_id`, `closed_at`) sont tous nullables : une reclamation
     * nait ouverte, le §15 interdit de les exiger a la creation.
     */
    public function up(): void
    {
        Schema::create('claims', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->char('customer_id', 26);
            $table->char('order_id', 26)->nullable();
            $table->char('order_service_id', 26)->nullable();
            $table->char('tour_id', 26)->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('claim_type', 64);
            $table->string('cause')->nullable();
            $table->text('decision')->nullable();
            $table->text('follow_up')->nullable();
            $table->string('result')->nullable();
            // Montant : DECIMAL(12,2), convention des prix et couts de la Phase 2.
            $table->decimal('cost', 12, 2)->nullable();
            $table->string('status', 32);
            $table->char('created_by', 26)->nullable();
            $table->char('responsible_user_id', 26)->nullable();
            $table->dateTime('created_at');
            $table->dateTime('closed_at')->nullable();

            $table->index('organization_id');
            $table->index('customer_id');
            $table->index('order_id');
            $table->index('order_service_id');
            $table->index('tour_id');
            $table->index('claim_type');
            $table->index('status');
            $table->index('responsible_user_id');
            $table->index('created_at');
            $table->index('closed_at');

            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->restrictOnDelete();
            // La reclamation survit a la commande qu'elle vise.
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
            $table->foreign('order_service_id')->references('id')->on('order_services')->nullOnDelete();
            $table->foreign('tour_id')->references('id')->on('tours')->nullOnDelete();
            // Le depart d'un collaborateur ne supprime pas ses dossiers.
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('responsible_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claims');
    }
};
