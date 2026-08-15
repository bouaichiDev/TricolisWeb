<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Decompte fournisseur.
     *
     * Ni `created_at` ni `updated_at` : la classe n'en declare aucun, a la
     * difference d'`Invoice`. L'historique reste porte par `audit_logs`.
     *
     * `subtotal` et `total` sont calcules ; `tax_total` est **saisi** : le §21
     * interdit d'inventer une TVA fournisseur, et aucune regle fiscale n'est
     * definie au modele.
     */
    public function up(): void
    {
        Schema::create('provider_settlements', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->char('provider_id', 26);
            $table->string('settlement_number');
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('status', 32);

            $table->unique(['organization_id', 'settlement_number']);
            $table->index('organization_id');
            $table->index('provider_id');
            $table->index('period_from');
            $table->index('period_to');
            $table->index('status');

            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign('provider_id')->references('id')->on('providers')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_settlements');
    }
};
