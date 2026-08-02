<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_order_lines', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('package_id', 26);
            $table->char('order_line_id', 26);
            $table->decimal('quantity', 12, 3);
            $table->timestamps();

            // Une ligne peut être répartie entre plusieurs colis, mais une même
            // ligne ne peut pas figurer deux fois dans le même colis : c'est la
            // quantité qui varie.
            $table->unique(['package_id', 'order_line_id']);
            $table->index('order_line_id');

            $table->foreign('package_id')->references('id')->on('packages')->cascadeOnDelete();
            $table->foreign('order_line_id')->references('id')->on('order_lines')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_order_lines');
    }
};
