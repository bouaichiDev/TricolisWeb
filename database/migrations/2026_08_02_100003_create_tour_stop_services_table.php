<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Service de commande planifie sur un arret.
     *
     * `TourStop "1" *-- "1..*" TourStopService` : composition, et au moins un
     * service par arret. La cardinalite minimale est tenue par l'application —
     * creation atomique du stop avec ses services, refus de supprimer le
     * dernier service actif.
     *
     * `is_active_assignment` distingue l'affectation courante de l'historique :
     * une ancienne affectation est desactivee, jamais supprimee.
     */
    public function up(): void
    {
        Schema::create('tour_stop_services', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('tour_stop_id', 26);
            $table->char('order_service_id', 26);
            $table->unsignedInteger('sequence_within_stop');
            $table->boolean('is_active_assignment')->default(true);
            $table->string('status', 32);

            $table->unique(['tour_stop_id', 'sequence_within_stop']);
            $table->index('tour_stop_id');
            $table->index('order_service_id');
            $table->index('is_active_assignment');
            $table->index('status');

            $table->foreign('tour_stop_id')->references('id')->on('tour_stops')->cascadeOnDelete();
            // Planifier un service ne doit pas permettre de le perdre.
            $table->foreign('order_service_id')->references('id')->on('order_services')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_stop_services');
    }
};
