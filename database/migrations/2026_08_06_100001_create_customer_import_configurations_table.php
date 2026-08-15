<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Configuration d'import d'un client.
     *
     * Le diagramme definit une **configuration**, pas un historique d'import :
     * ni fichier, ni ligne, ni erreur — le §8 l'interdit.
     *
     * `mapping` et `validation_rules` restent librement structurables : le
     * diagramme n'en definit pas le schema, et le §9 interdit de l'inventer.
     *
     * CASCADE sur le client : une configuration d'integration n'a aucun sens
     * sans lui, et ce n'est pas une piece comptable.
     */
    public function up(): void
    {
        Schema::create('customer_import_configurations', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('customer_id', 26);
            $table->string('name');
            $table->string('source_type', 64);
            $table->string('file_format', 32);
            $table->json('mapping')->nullable();
            $table->json('validation_rules')->nullable();
            $table->boolean('is_active')->default(true);

            $table->unique(['customer_id', 'name']);
            $table->index('customer_id');
            $table->index('source_type');
            $table->index('file_format');
            $table->index('is_active');

            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_import_configurations');
    }
};
