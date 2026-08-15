<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_catalog_items', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('catalog_id', 26);
            $table->string('article_code', 128);
            $table->string('barcode', 128)->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('weight', 12, 3)->default(0);
            $table->decimal('volume', 12, 4)->default(0);
            $table->decimal('length', 12, 3)->nullable();
            $table->decimal('width', 12, 3)->nullable();
            $table->decimal('height', 12, 3)->nullable();
            $table->string('status', 32)->default('active');
            $table->timestamps();

            $table->unique(['catalog_id', 'article_code']);
            $table->index(['catalog_id', 'status']);
            $table->index('barcode');

            $table->foreign('catalog_id')->references('id')->on('customer_catalogs')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_catalog_items');
    }
};
