<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_roles', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('organization_user_id', 26);
            $table->char('role_id', 26);

            $table->unique(['organization_user_id', 'role_id']);
            $table->index('role_id');

            $table->foreign('organization_user_id')->references('id')->on('organization_users')->cascadeOnDelete();
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');
    }
};
