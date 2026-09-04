<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Libellé et icône choisis par l'organisation pour une entrée de menu.
 *
 * Les deux colonnes sont **nullables, et le null a un sens** : « garder ce que
 * dit le catalogue ». Une organisation qui ne renomme rien conserve donc la
 * traduction livrée, et un changement de libellé en français profite à toutes
 * celles qui n'ont pas fait le choix inverse.
 *
 * Ce que ces colonnes ne portent pas : ni route, ni permission. Elles restent
 * en code — une route saisie en base qui ne correspond à rien dans le routeur
 * React donnerait « Page introuvable », et une permission inventée ouvrirait
 * un écran interdit. Renommer une entrée et la déplacer ne fabrique pas de
 * menu cassé ; en réécrire la destination, si.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_menu_items', function (Blueprint $table) {
            $table->string('label', 60)->nullable()->after('code');
            $table->string('icon', 64)->nullable()->after('label');
        });
    }

    public function down(): void
    {
        Schema::table('organization_menu_items', function (Blueprint $table) {
            $table->dropColumn(['label', 'icon']);
        });
    }
};
