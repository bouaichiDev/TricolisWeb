<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Arret d'une tournee.
     *
     * `Tour "1" *-- "0..*" TourStop` : composition, d'ou la cascade. Un arret
     * n'existe pas hors de sa tournee.
     *
     * `grouping_key` et `generation_mode` sont nullables : le diagramme n'en
     * enumere pas les valeurs, et un arret saisi a la main n'a pas de cle de
     * regroupement.
     */
    public function up(): void
    {
        Schema::create('tour_stops', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('tour_id', 26);
            $table->char('address_id', 26);
            $table->unsignedInteger('sequence');
            $table->string('grouping_key')->nullable();
            $table->string('generation_mode', 64)->nullable();
            $table->dateTime('planned_arrival_at')->nullable();
            $table->dateTime('planned_departure_at')->nullable();
            $table->dateTime('actual_arrival_at')->nullable();
            $table->dateTime('actual_departure_at')->nullable();
            $table->unsignedInteger('waiting_minutes')->default(0);
            $table->unsignedInteger('service_minutes')->default(0);
            $table->string('status', 32);

            $table->unique(['tour_id', 'sequence']);
            $table->index('tour_id');
            $table->index('address_id');
            $table->index('status');
            $table->index('grouping_key');

            $table->foreign('tour_id')->references('id')->on('tours')->cascadeOnDelete();
            // Une adresse encore planifiee ne disparait pas.
            $table->foreign('address_id')->references('id')->on('addresses')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_stops');
    }
};
