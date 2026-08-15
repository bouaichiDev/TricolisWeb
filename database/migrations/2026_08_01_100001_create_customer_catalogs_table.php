<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_catalogs', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('customer_id', 26);
            $table->string('code', 64);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 32)->default('active');
            $table->timestamps();

            $table->unique(['customer_id', 'code']);
            $table->index(['customer_id', 'status']);

            // Le catalogue n'a pas de sens sans son client.
            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_catalogs');
    }
};
