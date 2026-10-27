<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invitations au portail client.
 *
 * Seule l'empreinte SHA-256 du jeton est stockée : une fuite de la table ne
 * donne aucun lien d'activation utilisable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_user_invitations', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('customer_id', 26);
            $table->char('customer_user_id', 26)->nullable();
            $table->string('email');
            $table->char('role_id', 26)->nullable();
            $table->char('token_hash', 64)->unique();
            $table->string('status', 20)->default('pending');
            $table->timestamp('expires_at');
            $table->char('invited_by', 26)->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'email']);
            $table->index('status');

            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
            $table->foreign('customer_user_id')->references('id')->on('customer_users')->cascadeOnDelete();
            $table->foreign('role_id')->references('id')->on('roles')->nullOnDelete();
            $table->foreign('invited_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_user_invitations');
    }
};
