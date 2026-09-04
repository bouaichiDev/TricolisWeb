<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Compteur de numéros de facture.
     *
     * Même forme que celui des commandes, et pour la même raison : un numéro
     * dérivé d'un `count()` ou d'un `max()` se dédouble dès que deux factures
     * se créent en même temps. La ligne est verrouillée puis incrémentée dans
     * la transaction de création.
     *
     * Un compteur à part de celui des commandes : les deux séries n'ont pas le
     * même rythme, et une facture qui reprendrait la numérotation des commandes
     * laisserait des trous dans les deux.
     */
    public function up(): void
    {
        Schema::create('invoice_number_sequences', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->string('scope', 64)->default('default');
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['organization_id', 'scope', 'year']);

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_number_sequences');
    }
};
