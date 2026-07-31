<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_contacts', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->char('contact_id', 26);
            $table->string('entity_type', 64);
            $table->char('entity_id', 26);
            $table->string('contact_role', 32)->default('other');
            $table->boolean('is_primary')->default(false);
            $table->boolean('notify_by_email')->default(false);
            $table->boolean('notify_by_sms')->default(false);

            $table->unique(['entity_type', 'entity_id', 'contact_id', 'contact_role'], 'entity_contacts_link_unique');
            $table->index(['entity_type', 'entity_id']);
            $table->index('contact_id');

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('contact_id')->references('id')->on('contacts')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_contacts');
    }
};
