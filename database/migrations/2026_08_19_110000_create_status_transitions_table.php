<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Transitions autorisées entre deux statuts.
 *
 * Jusqu'ici la machine à états vivait dans `OrderStatus::allowedTransitions()`.
 * Un référentiel que l'administrateur gère et un cycle de vie figé dans le code
 * ne peuvent pas coexister : un statut créé à l'écran n'était atteignable par
 * aucune transition, donc inutile. La règle rejoint donc la donnée.
 *
 * **`is_manual` est porté par la transition, pas par le statut.** Passer une
 * commande en « planifiée » est légitime — c'est la planification qui le fait —
 * mais un opérateur ne doit pas pouvoir le déclarer à la main. Distinguer les
 * deux au niveau du statut, comme le faisait `manuallyAssignable()`, interdisait
 * la transition à tout le monde ou à personne.
 *
 * Les deux colonnes ajoutées à `statuses` portent le reste du comportement :
 * jusqu'où le contenu reste modifiable, et quels statuts exigent un motif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('statuses', function (Blueprint $table): void {
            // Le contenu — lignes, colis, services — reste-t-il modifiable ?
            $table->boolean('allows_content_changes')->default(false)->after('is_to_send');
            // Atteindre ce statut exige-t-il un motif ? (annulation)
            $table->boolean('requires_reason')->default(false)->after('allows_content_changes');
        });

        Schema::create('status_transitions', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('from_status_id', 26);
            $table->char('to_status_id', 26);
            // Un opérateur peut-il poser cette transition lui-même ?
            $table->boolean('is_manual')->default(true);
            $table->timestamps();

            $table->unique(['from_status_id', 'to_status_id']);
            $table->index('to_status_id');

            $table->foreign('from_status_id')->references('id')->on('statuses')->cascadeOnDelete();
            $table->foreign('to_status_id')->references('id')->on('statuses')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_transitions');

        Schema::table('statuses', function (Blueprint $table): void {
            $table->dropColumn(['allows_content_changes', 'requires_reason']);
        });
    }
};
