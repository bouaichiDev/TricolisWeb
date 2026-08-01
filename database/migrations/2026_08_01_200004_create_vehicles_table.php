<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vehicule d'un fournisseur.
     *
     * Precisions decimales reprises des Phases 1 et 2 : DECIMAL(12,3) pour une
     * masse, DECIMAL(12,4) pour un volume.
     */
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->unsignedBigInteger('legacy_id')->nullable();
            $table->char('provider_id', 26);
            $table->char('vehicle_type_id', 26);
            $table->string('code', 64);
            $table->string('registration_number', 32);
            $table->decimal('payload_capacity', 12, 3);
            $table->decimal('volume_capacity', 12, 4);
            $table->unsignedInteger('pallet_capacity');
            $table->string('status', 32);

            $table->unique(['provider_id', 'code']);
            // Une plaque identifie un vehicule physique : deux lignes portant la
            // meme immatriculation rendraient toute recherche terrain ambigue.
            $table->unique('registration_number');
            $table->index('provider_id');
            $table->index('vehicle_type_id');
            $table->index('status');
            $table->index('legacy_id');

            $table->foreign('provider_id')->references('id')->on('providers')->restrictOnDelete();
            // Supprimer un type ne doit pas supprimer les vehicules qui l'utilisent.
            $table->foreign('vehicle_type_id')->references('id')->on('vehicle_types')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
