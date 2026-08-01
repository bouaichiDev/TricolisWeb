<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Chauffeur d'un fournisseur.
     *
     * Aucun `organization_id` : l'appartenance organisationnelle passe par le
     * fournisseur, conformement au diagramme. Toute lecture joint donc
     * `providers`.
     */
    public function up(): void
    {
        Schema::create('drivers', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->unsignedBigInteger('legacy_id')->nullable();
            $table->char('provider_id', 26);
            // Un chauffeur n'a pas necessairement de compte sur la plateforme :
            // l'application chauffeur est hors perimetre.
            $table->char('user_id', 26)->nullable();
            $table->string('code', 64);
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->string('status', 32);

            $table->unique(['provider_id', 'code']);
            $table->index('provider_id');
            $table->index('user_id');
            $table->index('status');
            $table->index('legacy_id');
            $table->index('email');

            $table->foreign('provider_id')->references('id')->on('providers')->restrictOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};
