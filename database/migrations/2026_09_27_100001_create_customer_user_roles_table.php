<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rôles d'un membre du portail client.
 *
 * `user_roles` pointe vers `organization_users` : un compte client n'y a pas
 * sa place. Les rôles eux-mêmes restent ceux de la table `roles`, de portée
 * `customer`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_user_roles', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('customer_user_id', 26);
            $table->char('role_id', 26);

            $table->unique(['customer_user_id', 'role_id']);
            $table->index('role_id');

            $table->foreign('customer_user_id')->references('id')->on('customer_users')->cascadeOnDelete();
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_user_roles');
    }
};
