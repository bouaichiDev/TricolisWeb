<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Emplacement de stock, hierarchisable.
     *
     * `zone_code` reste un attribut : le §9 interdit une table `StockZone`.
     *
     * Aucun `legacy_id` : le §9 du prompt en mentionne un, le diagramme n'en
     * contient pas — le §1 donne priorite au diagramme.
     *
     * Le perimetre passe par `depot.agency.organization_id`, `depots` ne
     * portant pas d'organisation.
     */
    public function up(): void
    {
        Schema::create('stock_locations', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('depot_id', 26);
            $table->char('parent_location_id', 26)->nullable();
            $table->string('zone_code', 64)->nullable();
            $table->string('aisle', 32)->nullable();
            $table->string('rack', 32)->nullable();
            $table->string('level', 32)->nullable();
            $table->string('location_code', 64);
            $table->string('barcode', 128)->nullable();
            $table->string('status', 32);

            $table->unique(['depot_id', 'location_code']);
            $table->unique(['depot_id', 'barcode']);
            $table->index('depot_id');
            $table->index('parent_location_id');
            $table->index('zone_code');
            $table->index('status');

            $table->foreign('depot_id')->references('id')->on('depots')->restrictOnDelete();
            // RESTRICT : supprimer un emplacement parent ne doit pas orpheliner
            // ses enfants en silence. Le refus metier arrive avant, en 409.
            $table->foreign('parent_location_id')->references('id')->on('stock_locations')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_locations');
    }
};
