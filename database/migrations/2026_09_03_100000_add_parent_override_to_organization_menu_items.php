<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rattachement choisi par l'organisation : sortir une entrée de son groupe, ou
 * l'y faire entrer.
 *
 * **Deux colonnes plutôt qu'une, parce que `null` est déjà pris.** Partout
 * ailleurs dans cette table, `null` veut dire « suivre le catalogue » ; ici il
 * doit aussi pouvoir vouloir dire « au premier niveau ». Confondre les deux
 * rendrait la promotion d'une entrée indistinguable d'une absence de choix, et
 * l'entrée retomberait dans son groupe au premier rechargement.
 *
 * `parent_overridden` porte donc la décision, `parent_code` sa cible :
 *
 * | `parent_overridden` | `parent_code` | Résultat |
 * | --- | --- | --- |
 * | `false` | — | Le rattachement du catalogue |
 * | `true` | `null` | Entrée de premier niveau |
 * | `true` | `resources` | Entrée du groupe « Ressources » |
 *
 * Aucune clé étrangère : `parent_code` désigne une entrée du **catalogue**, qui
 * vit en code et non en base. C'est le résolveur qui vérifie qu'elle existe
 * encore, et rend l'entrée à son groupe d'origine sinon.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_menu_items', function (Blueprint $table) {
            $table->boolean('parent_overridden')->default(false)->after('icon');
            $table->string('parent_code', 64)->nullable()->after('parent_overridden');
        });
    }

    public function down(): void
    {
        Schema::table('organization_menu_items', function (Blueprint $table) {
            $table->dropColumn(['parent_overridden', 'parent_code']);
        });
    }
};
