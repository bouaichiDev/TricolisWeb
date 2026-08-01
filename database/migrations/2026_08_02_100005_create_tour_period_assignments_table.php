<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Affectation d'un service — et eventuellement d'un colis — a une periode.
     *
     * La classe ne porte que trois cles etrangeres : ni sequence, ni statut, ni
     * quantite, ni duree. Le §17 l'interdit explicitement.
     *
     * `package_id` est facultatif (`Package "0..1"`).
     */
    public function up(): void
    {
        Schema::create('tour_period_assignments', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('tour_period_id', 26);
            $table->char('tour_stop_service_id', 26);
            $table->char('package_id', 26)->nullable();

            // Une meme periode ne recoit pas deux fois le meme service pour le
            // meme colis. MySQL traite chaque NULL comme distinct : le doublon
            // sans colis est donc refuse par l'application, pas par l'index.
            $table->unique(['tour_period_id', 'tour_stop_service_id', 'package_id'], 'tour_period_assignments_unique');
            $table->index('tour_period_id');
            $table->index('tour_stop_service_id');
            $table->index('package_id');

            $table->foreign('tour_period_id')->references('id')->on('tour_periods')->cascadeOnDelete();
            // Le §14 exige de refuser la suppression d'un service encore affecte.
            $table->foreign('tour_stop_service_id')->references('id')->on('tour_stop_services')->restrictOnDelete();
            $table->foreign('package_id')->references('id')->on('packages')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_period_assignments');
    }
};
