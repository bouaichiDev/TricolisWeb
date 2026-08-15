<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ligne de facture.
     *
     * `Invoice "1" *-- "1..*" InvoiceLine` : composition, d'ou la cascade, et
     * au moins une ligne par facture — tenu par l'application.
     *
     * `OrderService "1" -- "0..1" InvoiceLine` : un service est facture au plus
     * une fois. D'ou l'unicite sur `order_service_id`. MySQL traitant chaque
     * NULL comme distinct, les lignes libres — frais de dossier,
     * regularisations — restent possibles en nombre.
     */
    public function up(): void
    {
        Schema::create('invoice_lines', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('invoice_id', 26);
            $table->char('order_service_id', 26)->nullable();
            $table->char('order_id', 26)->nullable();
            $table->unsignedInteger('line_number');
            $table->string('service_code', 64)->nullable();
            $table->string('description');
            $table->string('customer_order_reference')->nullable();
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 12, 2);
            // Pourcentages bornes a 0-100 par la validation.
            $table->decimal('discount_rate', 5, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            // Calcules a partir des quatre champs ci-dessus, jamais fournis.
            $table->decimal('total_excluding_tax', 12, 2)->default(0);
            $table->decimal('total_including_tax', 12, 2)->default(0);
            $table->dateTime('service_completed_at')->nullable();
            $table->string('status', 32);

            $table->unique(['invoice_id', 'line_number']);
            $table->unique('order_service_id');
            $table->index('invoice_id');
            $table->index('order_id');
            $table->index('service_code');
            $table->index('status');
            $table->index('service_completed_at');

            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
            // Supprimer une commande facturee effacerait la justification.
            $table->foreign('order_service_id')->references('id')->on('order_services')->restrictOnDelete();
            $table->foreign('order_id')->references('id')->on('orders')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
    }
};
