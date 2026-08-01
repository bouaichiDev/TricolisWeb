<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fournisseur de transport.
     *
     * Le diagramme ne definit ni timestamps, ni soft delete, ni adresse, ni
     * contact sur cette classe : l'historique des ecritures est porte par
     * `audit_logs`, et une liaison d'adresse passerait par `EntityAddress`.
     */
    public function up(): void
    {
        Schema::create('providers', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            // Reprise depuis l'ancienne plateforme : nul pour toute donnee creee par l'API.
            $table->unsignedBigInteger('legacy_id')->nullable();
            $table->char('organization_id', 26);
            $table->string('code', 64);
            $table->string('name');
            $table->string('provider_type', 64);
            $table->string('status', 32);

            $table->unique(['organization_id', 'code']);
            $table->index('organization_id');
            $table->index('status');
            $table->index('provider_type');
            $table->index('legacy_id');

            // Supprimer une organisation ne doit pas emporter ses fournisseurs.
            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('providers');
    }
};
