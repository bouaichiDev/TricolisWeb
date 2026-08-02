<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_sites', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('customer_id', 26);
            $table->char('address_id', 26);
            $table->string('code');
            $table->string('name');
            $table->string('site_type', 64)->nullable();
            $table->boolean('is_default')->default(false);
            $table->string('status', 20)->default('active');

            $table->unique(['customer_id', 'code']);
            $table->index('address_id');
            $table->index('status');

            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
            $table->foreign('address_id')->references('id')->on('addresses')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_sites');
    }
};
