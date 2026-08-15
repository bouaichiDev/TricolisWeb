<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Section de menu portée par la permission.
 *
 * Nullable, parce que les lignes existantes n'en ont pas au moment où la
 * colonne est ajoutée : `PermissionSeeder` les renseigne toutes juste après.
 * Un test vérifie qu'aucune permission ne reste sans section — c'est lui qui
 * tient l'invariant, la colonne se contentant de rendre la migration possible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->string('menu_section', 64)->nullable()->after('module');
            $table->index('menu_section');
        });
    }

    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropIndex(['menu_section']);
            $table->dropColumn('menu_section');
        });
    }
};
