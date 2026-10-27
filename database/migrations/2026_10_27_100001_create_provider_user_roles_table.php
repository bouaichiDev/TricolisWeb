<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rôles d'un membre du portail fournisseur.
 *
 * Les rôles restent ceux de la table `roles`, de portée `provider` : aucun
 * RBAC parallèle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_user_roles', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('provider_user_id', 26);
            $table->char('role_id', 26);

            $table->unique(['provider_user_id', 'role_id']);
            $table->index('role_id');

            $table->foreign('provider_user_id')->references('id')->on('provider_users')->cascadeOnDelete();
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_user_roles');
    }
};
