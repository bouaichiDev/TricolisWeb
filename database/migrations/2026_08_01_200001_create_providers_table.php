<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fournisseur de transport.
     *
     * Le diagramme porte l'adresse et le contact en cle etrangere directe, et
     * non via `EntityAddress` : `Provider "0..*" --> "0..1" Address`. Les deux
     * liens sont donc facultatifs. Ni timestamps ni soft delete : l'historique
     * des ecritures est porte par `audit_logs`.
     */
    public function up(): void
    {
        Schema::create('providers', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->char('address_id', 26)->nullable();
            $table->char('contact_id', 26)->nullable();
            $table->string('code', 64);
            $table->string('name');
            $table->string('status', 32);

            $table->unique(['organization_id', 'code']);
            $table->index('organization_id');
            $table->index('address_id');
            $table->index('contact_id');
            $table->index('status');

            // Supprimer une organisation ne doit pas emporter ses fournisseurs.
            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            // Une adresse encore referencee ne peut pas disparaitre en silence.
            $table->foreign('address_id')->references('id')->on('addresses')->restrictOnDelete();
            $table->foreign('contact_id')->references('id')->on('contacts')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('providers');
    }
};
