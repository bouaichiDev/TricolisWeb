<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Autorise les rôles de portée plateforme.
 *
 * Un rôle `scope = platform` n'appartient à aucune organisation : c'est ce qui
 * le distingue d'un rôle local et ce qui l'empêche d'être créé depuis
 * l'administration d'un organisme, où l'organisation active est toujours
 * imposée. Rendre `organization_id` nullable est donc la traduction exacte de
 * cette différence, et non un assouplissement de contrainte.
 *
 * MySQL admet plusieurs lignes `NULL` dans un index unique : la contrainte
 * `unique(organization_id, code)` continue d'interdire deux rôles de même code
 * dans une organisation, sans interdire plusieurs rôles plateforme.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->char('organization_id', 26)->nullable()->change();
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        // Les rôles plateforme n'ont pas d'organisation d'accueil : les
        // conserver rendrait la colonne non renseignable. Ils sont donc
        // supprimés avant de rétablir la contrainte.
        Schema::table('roles', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
        });

        DB::table('roles')->whereNull('organization_id')->delete();

        Schema::table('roles', function (Blueprint $table) {
            $table->char('organization_id', 26)->nullable(false)->change();
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });
    }
};
