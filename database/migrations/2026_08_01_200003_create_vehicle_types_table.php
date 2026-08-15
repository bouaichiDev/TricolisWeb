<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Referentiel des types de vehicule, propre a une organisation.
     */
    public function up(): void
    {
        Schema::create('vehicle_types', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->string('code', 64);
            $table->string('name');
            $table->string('status', 32);

            $table->unique(['organization_id', 'code']);
            $table->index('organization_id');
            $table->index('status');

            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_types');
    }
};
