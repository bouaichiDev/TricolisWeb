<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Configuration d'acces API d'un client.
     *
     * `api_key_hash` est une empreinte **SHA-256**, en CHAR(64) : deterministe,
     * donc consultable par index unique a chaque requete. Bcrypt serait ici
     * contre-productif — trop lent pour une verification par appel, et non
     * consultable.
     *
     * Un hash rapide suffit parce que la cle est **generee** (64 caracteres
     * aleatoires), pas choisie : elle ne se casse pas par force brute. C'est le
     * raisonnement de Laravel Sanctum, deja utilise dans le projet.
     *
     * La cle en clair n'est jamais stockee, ni journalisee.
     */
    public function up(): void
    {
        Schema::create('customer_api_configurations', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('customer_id', 26);
            $table->string('name');
            $table->char('api_key_hash', 64);
            $table->json('allowed_ips')->nullable();
            $table->json('permissions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->dateTime('last_used_at')->nullable();

            $table->unique(['customer_id', 'name']);
            // Unique globalement : une cle designe une configuration et une
            // seule, et c'est cette unicite qui invalide l'ancienne cle a la
            // rotation.
            $table->unique('api_key_hash');
            $table->index('customer_id');
            $table->index('is_active');
            $table->index('last_used_at');

            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_api_configurations');
    }
};
