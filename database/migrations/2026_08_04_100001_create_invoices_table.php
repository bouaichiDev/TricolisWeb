<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Facture client.
     *
     * `created_at` existe parce que le diagramme declare `createdAt` ;
     * `updated_at` non, pour la meme raison. Aucun `legacy_id` : le §6 du prompt
     * en mentionne un, le diagramme n'en contient pas — et le §1 donne priorite
     * au diagramme.
     *
     * Les trois totaux sont calcules a partir des lignes : ils ne sont jamais
     * acceptes en entree.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->char('customer_id', 26);
            $table->string('invoice_number');
            $table->date('invoice_date');
            // Une facture ponctuelle ne couvre pas de periode.
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->char('currency_code', 3);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('external_reference')->nullable();
            $table->text('remark')->nullable();
            $table->string('status', 32);
            $table->dateTime('created_at');

            // Meme portee que orders.order_number.
            $table->unique(['organization_id', 'invoice_number']);
            $table->index('organization_id');
            $table->index('customer_id');
            $table->index('invoice_date');
            $table->index('period_from');
            $table->index('period_to');
            $table->index('currency_code');
            $table->index('status');
            $table->index('external_reference');
            $table->index('created_at');

            // Une facture est une piece comptable : rien ne disparait sous elle.
            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
