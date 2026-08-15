<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Article de stock d'un client.
     *
     * Pas d'`organization_id` : la classe n'en declare pas, et le §2 interdit
     * de l'ajouter. Le perimetre passe par `customer.organization_id`.
     *
     * Ni quantite ni emplacement ici : le stock reel vit dans `stock_balances`.
     */
    public function up(): void
    {
        Schema::create('stock_items', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('customer_id', 26);
            $table->char('catalog_item_id', 26)->nullable();
            $table->string('article_code', 64);
            $table->string('barcode', 128)->nullable();
            $table->string('description')->nullable();
            $table->string('status', 32);

            // Le code article identifie la reference chez le client.
            $table->unique(['customer_id', 'article_code']);
            // Un code-barres scanne doit designer un article et un seul — chez
            // ce client. MySQL traitant chaque NULL comme distinct, les articles
            // sans code-barres restent possibles en nombre.
            $table->unique(['customer_id', 'barcode']);
            $table->index('customer_id');
            $table->index('catalog_item_id');
            $table->index('status');

            $table->foreign('customer_id')->references('id')->on('customers')->restrictOnDelete();
            $table->foreign('catalog_item_id')->references('id')->on('customer_catalog_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_items');
    }
};
