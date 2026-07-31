<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->string('code');
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('payment_mode', 40)->nullable();
            $table->string('communication_mode', 40)->nullable();
            $table->boolean('catalog_enabled')->default(false);
            $table->boolean('stock_enabled')->default(false);
            $table->boolean('package_enabled')->default(false);
            $table->boolean('appointment_enabled')->default(false);
            $table->boolean('tracking_enabled')->default(false);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'name']);

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
