<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_service_packages', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('order_service_id', 26);
            $table->char('package_id', 26);
            $table->decimal('quantity', 12, 3)->default(1);
            $table->text('handling_instructions')->nullable();
            $table->string('status', 32)->default('pending');
            $table->timestamps();

            $table->unique(['order_service_id', 'package_id']);
            $table->index('package_id');

            $table->foreign('order_service_id')->references('id')->on('order_services')->cascadeOnDelete();
            $table->foreign('package_id')->references('id')->on('packages')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_service_packages');
    }
};
