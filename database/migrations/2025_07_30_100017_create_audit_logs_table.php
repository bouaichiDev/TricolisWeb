<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->char('user_id', 26)->nullable();
            $table->string('action', 64);
            $table->string('entity_type', 64);
            $table->char('entity_id', 26);
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at');

            $table->index(['organization_id', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
            $table->index('action');
            $table->index('user_id');

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
