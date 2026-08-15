<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Chauffeur d'un fournisseur.
     *
     * Le diagramme porte `organizationId` directement sur la classe, en plus du
     * rattachement au fournisseur : l'isolation ne depend donc pas d'une
     * jointure. Les deux valeurs doivent rester coherentes, ce qu'imposent les
     * Actions.
     *
     * `Driver "0..*" --> "0..1" Address` : adresse et contact facultatifs.
     */
    public function up(): void
    {
        Schema::create('drivers', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->char('provider_id', 26);
            $table->char('address_id', 26)->nullable();
            $table->char('contact_id', 26)->nullable();
            $table->string('code', 64);
            $table->string('name');
            $table->string('status', 32);

            $table->unique(['provider_id', 'code']);
            $table->index('organization_id');
            $table->index('provider_id');
            $table->index('address_id');
            $table->index('contact_id');
            $table->index('status');

            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign('provider_id')->references('id')->on('providers')->restrictOnDelete();
            $table->foreign('address_id')->references('id')->on('addresses')->restrictOnDelete();
            $table->foreign('contact_id')->references('id')->on('contacts')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};
