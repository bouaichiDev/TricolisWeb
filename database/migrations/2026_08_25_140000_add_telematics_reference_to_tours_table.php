<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La référence de la tournée chez le fournisseur de télématique.
 *
 * C'est par elle que Tricolis retrouve les positions : chez Flespi, le filtre
 * s'écrit `Planid=<référence>`. Sans elle, une tournée n'est pas suivie — et
 * c'est le cas normal, la colonne est donc nullable.
 *
 * Une chaîne libre et non un identifiant typé : chaque fournisseur nomme ses
 * courses à sa façon, et la nôtre n'a pas à ressembler à la sienne.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table): void {
            $table->string('telematics_reference', 128)->nullable()->after('driver_id');
            $table->index('telematics_reference');
        });
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table): void {
            $table->dropIndex(['telematics_reference']);
            $table->dropColumn('telematics_reference');
        });
    }
};
