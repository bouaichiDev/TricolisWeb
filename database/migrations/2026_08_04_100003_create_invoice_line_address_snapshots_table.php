<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Copie figee de l'adresse d'une ligne de facture.
     *
     * **Aucune cle etrangere vers `addresses`.** C'est tout l'objet du
     * snapshot : une modification d'adresse ne doit jamais remonter dans une
     * facture deja emise.
     *
     * Tous les champs sont nullables : rien ne garantit que l'adresse d'origine
     * etait complete, et un snapshot doit savoir figer une adresse partielle.
     */
    public function up(): void
    {
        Schema::create('invoice_line_address_snapshots', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('invoice_line_id', 26);
            $table->string('address_code', 64)->nullable();
            $table->string('name')->nullable();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();

            // `InvoiceLine "1" *-- "0..1"` : au plus un snapshot par ligne.
            $table->unique('invoice_line_id');

            $table->foreign('invoice_line_id')->references('id')->on('invoice_lines')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_line_address_snapshots');
    }
};
