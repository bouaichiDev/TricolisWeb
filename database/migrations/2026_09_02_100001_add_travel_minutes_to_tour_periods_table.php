<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le temps de route de chaque segment.
 *
 * `tour_periods` portait la distance du segment mais pas sa duree : celle-ci
 * n'existait qu'additionnee, dans `tours.driving_time_minutes`. Un total ne se
 * redecoupe pas — impossible d'annoncer entre deux arrets le temps qui les
 * separe. La colonne garde ce que le calculateur d'itineraire renvoyait deja
 * pour chaque segment et qu'on jetait.
 *
 * Les periodes existantes valent `0` jusqu'au prochain calcul d'itineraire :
 * l'ecran distingue un segment sans duree connue d'un segment instantane.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tour_periods', function (Blueprint $table): void {
            $table->unsignedInteger('travel_minutes')->default(0)->after('waiting_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('tour_periods', function (Blueprint $table): void {
            $table->dropColumn('travel_minutes');
        });
    }
};
