<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Appartenance d'un compte au portail d'un client (`CustomerUser`).
 *
 * Le pendant de `organization_users` côté client : le même `User` peut être
 * collaborateur d'un transporteur et membre du portail d'un client, sans
 * doublon de compte. C'est ce rattachement, et lui seul, qui détermine le
 * Customer courant d'une requête du portail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_users', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('customer_id', 26);
            $table->char('user_id', 26);
            $table->string('status', 20)->default('invited');
            $table->boolean('is_owner')->default(false);
            $table->char('invited_by', 26)->nullable();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'user_id']);
            $table->index('user_id');
            $table->index(['customer_id', 'status']);

            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('invited_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_users');
    }
};
