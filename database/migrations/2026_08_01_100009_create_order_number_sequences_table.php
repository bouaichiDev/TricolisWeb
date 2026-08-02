<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Compteur de numéros de commande.
     *
     * Le numéro ne peut pas être dérivé d'un `count()` : deux créations
     * simultanées liraient la même valeur. La ligne de compteur est donc
     * verrouillée (`lockForUpdate`) puis incrémentée dans la transaction de
     * création, ce qui sérialise les créations concurrentes d'une même série.
     *
     * La granularité (organisation + série) permet d'introduire plus tard une
     * numérotation par agence sans changer de mécanisme.
     */
    public function up(): void
    {
        Schema::create('order_number_sequences', function (Blueprint $table): void {
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
        Schema::dropIfExists('order_number_sequences');
    }
};
