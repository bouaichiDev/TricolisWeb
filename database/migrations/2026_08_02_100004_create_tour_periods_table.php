<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Periode d'une tournee.
     *
     * `Tour "1" *-- "0..*" TourPeriod` : composition, d'ou la cascade.
     * `TourStop "0..1" -- "0..*" TourPeriod` : association facultative — une
     * periode de conduite entre deux arrets n'appartient a aucun arret.
     *
     * `period_type` et `status` sont obligatoires et sans defaut : le diagramme
     * ne les enumere pas, poser une valeur par defaut serait l'inventer.
     */
    public function up(): void
    {
        Schema::create('tour_periods', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('tour_id', 26);
            $table->char('tour_stop_id', 26)->nullable();
            $table->string('period_type', 64);
            $table->unsignedInteger('sequence');
            $table->dateTime('planned_start_at')->nullable();
            $table->dateTime('planned_end_at')->nullable();
            $table->dateTime('actual_start_at')->nullable();
            $table->dateTime('actual_end_at')->nullable();
            $table->unsignedInteger('break_minutes')->default(0);
            $table->unsignedInteger('service_minutes')->default(0);
            $table->unsignedInteger('waiting_minutes')->default(0);
            $table->unsignedBigInteger('distance_meters')->default(0);
            $table->text('internal_remark')->nullable();
            $table->string('status', 32);

            $table->unique(['tour_id', 'sequence']);
            $table->index('tour_id');
            $table->index('tour_stop_id');
            $table->index('period_type');
            $table->index('status');
            $table->index('planned_start_at');

            $table->foreign('tour_id')->references('id')->on('tours')->cascadeOnDelete();
            // SET NULL et non RESTRICT : la suppression d'un arret encore
            // reference est refusee en 409 par l'application, et la cascade
            // depuis la tournee doit rester executable quel que soit l'ordre
            // choisi par le moteur.
            $table->foreign('tour_stop_id')->references('id')->on('tour_stops')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_periods');
    }
};
