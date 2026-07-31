<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_lines', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('order_id', 26);
            $table->char('catalog_item_id', 26)->nullable();
            $table->char('parent_line_id', 26)->nullable();
            $table->string('external_reference')->nullable();
            $table->string('article_code')->nullable();
            $table->string('barcode')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('quantity', 12, 3);
            $table->decimal('reserved_quantity', 12, 3)->default(0);
            $table->decimal('prepared_quantity', 12, 3)->default(0);
            $table->decimal('delivered_quantity', 12, 3)->default(0);
            $table->decimal('weight', 12, 3)->default(0);
            $table->decimal('volume', 12, 4)->default(0);
            $table->decimal('length', 12, 3)->nullable();
            $table->decimal('width', 12, 3)->nullable();
            $table->decimal('height', 12, 3)->nullable();
            $table->decimal('purchase_price', 12, 2)->nullable();
            $table->decimal('selling_price', 12, 2)->nullable();
            $table->string('status', 32)->default('active');
            $table->index('order_id');
            $table->index('article_code');
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('parent_line_id')->references('id')->on('order_lines')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_lines');
    }
};
