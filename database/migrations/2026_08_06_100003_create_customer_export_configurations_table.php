<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Configuration d'export d'un client.
     *
     * Tous les champs de connexion sont nullables : leur necessite depend du
     * transport, et le §19 interdit d'ajouter des colonnes par transport. Seul
     * `host` est rendu conditionnellement obligatoire par la validation.
     *
     * `encrypted_password` est chiffre par `Crypt` de Laravel — reversible,
     * puisque le transport doit presenter le mot de passe au serveur distant.
     * Il n'est jamais retourne, ni chiffre ni dechiffre.
     *
     * `format` et `transport` sont adosses aux enums du diagramme, mais stockes
     * en VARCHAR : convention des Phases 2 et 4.
     */
    public function up(): void
    {
        Schema::create('customer_export_configurations', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('customer_id', 26);
            $table->string('name');
            $table->string('export_type', 64);
            $table->string('format', 16);
            $table->string('transport', 16);
            $table->string('host')->nullable();
            $table->unsignedInteger('port')->nullable();
            $table->string('username')->nullable();
            $table->text('encrypted_password')->nullable();
            $table->string('remote_directory')->nullable();
            $table->string('file_name_pattern')->nullable();
            $table->string('encoding', 32)->nullable();
            $table->string('frequency', 64)->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);

            $table->unique(['customer_id', 'name']);
            $table->index('customer_id');
            $table->index('export_type');
            $table->index('format');
            $table->index('transport');
            $table->index('frequency');
            $table->index('is_active');

            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_export_configurations');
    }
};
