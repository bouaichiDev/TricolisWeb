<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->string('code');
            $table->string('name');
            $table->string('unit', 32);
            $table->unsignedInteger('default_duration_minutes');
            $table->boolean('billable_to_customer');
            $table->boolean('payable_to_provider');
            $table->boolean('requires_address');
            $table->boolean('requires_contact');
            $table->string('status', 32);
            $table->unique(['organization_id', 'code']);
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
