<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ce qu'une facture close a réellement montré.
 *
 * Une facture close est un engagement : le client l'a reçue, son comptable l'a
 * classée. Si la relecture rejouait le modèle courant, corriger une mention
 * légale en septembre réécrirait toutes les factures de l'année — et deux
 * personnes regardant la même facture à deux dates y liraient deux documents.
 *
 * `rendered_body` fige donc le document produit à la clôture. C'est **le
 * résultat du rendu**, pas une copie du modèle : le §0.23 interdit d'y répondre
 * par une seconde table de modèles.
 *
 * `template_id` ne sert qu'à l'audit — savoir lequel a servi — et reste en
 * RESTRICT : un modèle ayant produit une facture ne se supprime pas. Le
 * document, lui, ne dépend plus de lui une fois figé.
 *
 * Les trois colonnes sont nulles pour une facture en brouillon : elle se rend à
 * la demande, depuis le modèle du moment, et c'est bien ce qu'un aperçu doit
 * montrer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->char('template_id', 26)->nullable()->after('status');
            $table->longText('rendered_body')->nullable()->after('template_id');
            $table->dateTime('rendered_at')->nullable()->after('rendered_body');

            $table->index('template_id');
            $table->foreign('template_id')->references('id')->on('templates')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropForeign(['template_id']);
            $table->dropIndex(['template_id']);
            $table->dropColumn(['template_id', 'rendered_body', 'rendered_at']);
        });
    }
};
