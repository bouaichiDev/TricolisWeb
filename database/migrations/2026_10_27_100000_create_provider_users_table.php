<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Appartenance d'un compte au portail d'un fournisseur (`ProviderUser`).
 *
 * Le pendant de `customer_users` côté fournisseur : c'est ce rattachement, et
 * lui seul, qui détermine le fournisseur courant d'une requête du portail.
 * Il ne s'écrit que depuis le back-office — le portail fournisseur ne gère ni
 * comptes ni rôles.
 *
 * `restrictOnDelete` sur le fournisseur : ses accès ne disparaissent pas en
 * silence avec lui, ils se désactivent d'abord.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_users', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('provider_id', 26);
            $table->char('user_id', 26);
            $table->string('status', 20)->default('invited');
            $table->char('invited_by', 26)->nullable();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();

            $table->unique(['provider_id', 'user_id']);
            $table->index('user_id');
            $table->index(['provider_id', 'status']);

            $table->foreign('provider_id')->references('id')->on('providers')->restrictOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('invited_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_users');
    }
};
