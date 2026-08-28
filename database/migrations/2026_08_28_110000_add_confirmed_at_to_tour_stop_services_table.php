<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quand une affectation devient visible pour le reste de l'application.
 *
 * Une composition sur carte ne doit pas transparaître dans les colonnes tant
 * que le planificateur n'a pas confirmé — décision du 28 août 2026. Il faut
 * donc distinguer ce qui est acquis de ce qui est en cours.
 *
 * **Une date explicite, et non une comparaison d'horloges.** La première version
 * comparait la création de l'affectation à la prise de la tournée ; or
 * `tour_stop_services` ne porte aucun horodatage — `$timestamps = false` — et la
 * comparaison portait sur `null`, si bien que tout restait visible. Une colonne
 * qui dit ce qu'elle veut dire vaut mieux qu'une déduction qui dépend d'un
 * détail de schéma.
 *
 * Nulle = pas encore confirmée. Renseignée = acquise, visible partout. Les
 * affectations existantes le sont : elles ont été faites avant que la
 * confirmation n'existe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tour_stop_services', function (Blueprint $table): void {
            $table->dateTime('confirmed_at')->nullable()->after('is_active_assignment');
            $table->index('confirmed_at');
        });

        // Tout ce qui precede cette migration a ete pose hors composition : le
        // laisser nul le ferait disparaitre des colonnes sans raison.
        Schema::getConnection()->table('tour_stop_services')->update(['confirmed_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('tour_stop_services', function (Blueprint $table): void {
            $table->dropIndex(['confirmed_at']);
            $table->dropColumn('confirmed_at');
        });
    }
};
