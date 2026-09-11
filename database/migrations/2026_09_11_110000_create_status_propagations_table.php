<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ce qu'un statut entraîne sur les entités voisines.
 *
 * Une commande, ses services, ses colis et ses lignes vivent ensemble : quand
 * tous les colis d'un service sont chargés, le service l'est aussi, et la
 * commande avec lui. Cette règle était nulle part — ni dans le code, ni à
 * l'écran — et chaque statut devait donc être repassé à la main, entité par
 * entité.
 *
 * **Une règle est une paire de statuts.** `from_status_id` dit ce qui arrive,
 * `to_status_id` ce que cela entraîne ; chacun porte déjà son entité, et le sens
 * — vers le parent ou vers les enfants — se déduit de la hiérarchie que
 * `StatusRelations` décrit. Il n'y a donc ni entité ni direction à saisir, donc
 * rien à contredire.
 *
 * **`mode` ne concerne que la remontée** : faut-il que *tous* les colis soient
 * chargés pour que le service le devienne (`all`), ou *un seul* suffit-il
 * (`any`) ? Vers les enfants, la question ne se pose pas.
 *
 * Les deux clés étrangères suppriment la règle avec le statut : une règle qui
 * désigne un statut disparu ne veut plus rien dire.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_propagations', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('from_status_id', 26);
            $table->char('to_status_id', 26);
            // `all` : tous les enfants doivent y être. `any` : un seul suffit.
            $table->string('mode', 8)->default('all');
            $table->boolean('active')->default(true);
            $table->timestamps();

            // Une même paire ne se déclare qu'une fois : deux règles identiques
            // ne feraient que doubler le travail.
            $table->unique(['from_status_id', 'to_status_id']);
            $table->index(['from_status_id', 'active']);

            $table->foreign('from_status_id')->references('id')->on('statuses')->cascadeOnDelete();
            $table->foreign('to_status_id')->references('id')->on('statuses')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_propagations');
    }
};
