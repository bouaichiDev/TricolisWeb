<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tournée — racine de l'agregat de planification.
     *
     * Depot, Provider, Driver et Vehicle sont facultatifs : le diagramme les
     * pose en `0..1`, et une tournee se planifie avant d'etre affectee.
     *
     * Ni timestamps ni soft delete : la classe n'en definit aucun. L'historique
     * des ecritures est porte par `audit_logs`.
     */
    public function up(): void
    {
        Schema::create('tours', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->string('tour_number');
            $table->date('tour_date');
            $table->char('agency_id', 26);
            $table->char('depot_id', 26)->nullable();
            $table->char('provider_id', 26)->nullable();
            $table->char('vehicle_id', 26)->nullable();
            $table->char('driver_id', 26)->nullable();
            $table->string('tour_type', 64)->nullable();
            $table->text('instructions')->nullable();
            $table->dateTime('planned_start_at')->nullable();
            $table->dateTime('planned_end_at')->nullable();
            $table->dateTime('actual_start_at')->nullable();
            $table->dateTime('actual_end_at')->nullable();
            // Totaux : UNSIGNED et defaut 0, comme orders.weight / package_count.
            // Une somme sur des NULL produirait NULL et casserait le recalcul.
            $table->decimal('total_weight', 12, 3)->default(0);
            $table->decimal('total_volume', 12, 4)->default(0);
            $table->unsignedInteger('total_packages')->default(0);
            $table->unsignedInteger('total_customers')->default(0);
            $table->unsignedInteger('driving_time_minutes')->default(0);
            $table->unsignedInteger('working_time_minutes')->default(0);
            $table->unsignedBigInteger('distance_meters')->default(0);
            $table->string('status', 32);

            // Meme portee que orders.order_number : unique dans l'organisation.
            $table->unique(['organization_id', 'tour_number']);
            $table->index('organization_id');
            $table->index('agency_id');
            $table->index('depot_id');
            $table->index('provider_id');
            $table->index('driver_id');
            $table->index('vehicle_id');
            $table->index('tour_date');
            $table->index('status');
            $table->index('tour_type');

            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign('agency_id')->references('id')->on('agencies')->restrictOnDelete();
            // Supprimer un depot, un fournisseur, un vehicule ou un chauffeur ne
            // doit pas detruire la tournee qui les a utilises.
            $table->foreign('depot_id')->references('id')->on('depots')->nullOnDelete();
            $table->foreign('provider_id')->references('id')->on('providers')->nullOnDelete();
            $table->foreign('vehicle_id')->references('id')->on('vehicles')->nullOnDelete();
            $table->foreign('driver_id')->references('id')->on('drivers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tours');
    }
};
