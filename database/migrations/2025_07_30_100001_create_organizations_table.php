<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('registration_number')->nullable();
            $table->string('tax_number')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('preferred_language', 10)->default('fr');
            $table->string('timezone', 64)->default('Europe/Paris');
            $table->string('currency_code', 3)->default('EUR');
            $table->string('status', 20)->default('pending');
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
