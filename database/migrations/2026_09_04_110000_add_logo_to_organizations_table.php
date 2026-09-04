<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Logo de l'organisation.
 *
 * Deux colonnes, et non le seul chemin : le PDF de facture a besoin du **type
 * MIME** pour composer son `data:` URI, et l'aller chercher dans le fichier à
 * chaque rendu ferait une lecture disque de plus par facture.
 *
 * Le fichier vit sur le disque `local`, pas dans `public` : une organisation
 * n'a pas à voir le logo d'une autre, et un chemin devinable le donnerait à
 * qui l'essaie. Il se sert par une route qui vérifie l'appartenance.
 *
 * `settings` aurait pu l'accueillir — c'est un JSON libre — mais un fichier
 * n'est pas un réglage : il se remplace, se supprime et se sert. Deux colonnes
 * nommées disent ce qu'elles portent, là où une clé enfouie dans un JSON se
 * découvre en lisant le code qui l'écrit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('logo_path', 255)->nullable()->after('settings');
            $table->string('logo_mime_type', 64)->nullable()->after('logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['logo_path', 'logo_mime_type']);
        });
    }
};
