<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_addresses', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->char('address_id', 26);
            $table->string('entity_type', 64);
            $table->char('entity_id', 26);
            $table->string('address_type', 64)->nullable();
            $table->boolean('is_default')->default(false);

            $table->unique(['entity_type', 'entity_id', 'address_id', 'address_type'], 'entity_addresses_link_unique');
            $table->index(['entity_type', 'entity_id']);
            $table->index('address_id');

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('address_id')->references('id')->on('addresses')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_addresses');
    }
};
