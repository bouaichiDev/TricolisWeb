<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invitations au portail fournisseur.
 *
 * Seule l'empreinte SHA-256 du jeton est stockée : une fuite de la table ne
 * donne aucun lien d'activation utilisable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_user_invitations', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('provider_id', 26);
            $table->char('provider_user_id', 26)->nullable();
            $table->string('email');
            $table->char('role_id', 26)->nullable();
            $table->char('token_hash', 64)->unique();
            $table->string('status', 20)->default('pending');
            $table->timestamp('expires_at');
            $table->char('invited_by', 26)->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index(['provider_id', 'email']);
            $table->index('status');

            $table->foreign('provider_id')->references('id')->on('providers')->cascadeOnDelete();
            $table->foreign('provider_user_id')->references('id')->on('provider_users')->cascadeOnDelete();
            $table->foreign('role_id')->references('id')->on('roles')->nullOnDelete();
            $table->foreign('invited_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_user_invitations');
    }
};
