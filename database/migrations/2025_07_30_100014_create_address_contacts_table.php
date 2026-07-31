<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('address_contacts', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('address_id', 26);
            $table->char('contact_id', 26);
            $table->string('contact_role', 32)->default('other');
            $table->boolean('is_primary')->default(false);

            $table->unique(['address_id', 'contact_id', 'contact_role']);
            $table->index('contact_id');

            $table->foreign('address_id')->references('id')->on('addresses')->cascadeOnDelete();
            $table->foreign('contact_id')->references('id')->on('contacts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('address_contacts');
    }
};
