<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quels changements de statut deviennent des événements visibles par le client.
 *
 * Le chauffeur pose un statut — « chargé » sur un service — et l'événement
 * apparaît dans le parcours, sans que personne ne le saisisse.
 *
 * Table séparée de `statuses`, qui décrit ce qu'un statut *signifie* en
 * interne. Ici, on décide de ce qui est *montré*, sous quel titre, et à quelle
 * place du parcours. Le libellé interne « chargé » devient « Votre commande est
 * en route vers vous » ; ce n'est pas la même donnée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracking_event_definitions', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);

            // Alias de morph map, pas une enumeration : recopier ici les 39
            // sources les ferait diverger a la premiere entite ajoutee.
            $table->string('source_type', 64);
            $table->string('status_code', 64);

            $table->string('code', 64);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('icon', 64)->nullable();
            $table->unsignedSmallInteger('position')->default(0);

            // L'etape se suit sur une carte. Un booleen, pas une adresse : le
            // contrat de l'API de position n'est pas arrete, et l'inventer
            // creerait un accord que personne n'a signe.
            $table->boolean('is_live')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();

            // Un statut, un evenement : deux definitions pour le meme couple
            // produiraient deux evenements pour un seul changement.
            $table->unique(['organization_id', 'source_type', 'status_code'], 'ted_source_status_unique');
            $table->unique(['organization_id', 'code'], 'ted_code_unique');
            $table->index(['organization_id', 'active', 'position']);

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_event_definitions');
    }
};
