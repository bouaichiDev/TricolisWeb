<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('order_id', 26);
            $table->char('parent_package_id', 26)->nullable();
            $table->char('package_type_id', 26)->nullable();
            $table->char('grouping_type_id', 26)->nullable();
            // Emplacement de stock courant : la colonne figure au diagramme mais
            // `stock_locations` relève d'une phase ultérieure. Aucune contrainte
            // n'est posée tant que la table n'existe pas.
            $table->char('current_stock_location_id', 26)->nullable();
            $table->string('barcode', 128)->nullable();
            $table->string('reference')->nullable();
            $table->string('description')->nullable();
            $table->decimal('quantity', 12, 3)->default(1);
            $table->decimal('weight', 12, 3)->default(0);
            $table->decimal('volume', 12, 4)->default(0);
            $table->decimal('length', 12, 3)->nullable();
            $table->decimal('width', 12, 3)->nullable();
            $table->decimal('height', 12, 3)->nullable();
            $table->string('status', 32)->default('draft');
            $table->timestamps();

            // Le code-barres identifie physiquement le colis chez le transporteur :
            // il doit être unique globalement, pas seulement dans la commande.
            $table->unique('barcode');
            $table->index(['order_id', 'status']);
            $table->index('parent_package_id');
            $table->index('current_stock_location_id');

            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('parent_package_id')->references('id')->on('packages')->nullOnDelete();
            $table->foreign('package_type_id')->references('id')->on('package_types')->restrictOnDelete();
            $table->foreign('grouping_type_id')->references('id')->on('grouping_types')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
