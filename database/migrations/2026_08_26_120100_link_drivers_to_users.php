<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un chauffeur peut être celui du transporteur, et porte son compte.
 *
 * Deux changements de même origine : le fournisseur devient facultatif — un
 * transporteur emploie ses propres chauffeurs — et le chauffeur se rattache à
 * un compte utilisateur, celui avec lequel il ouvrira l'application mobile.
 *
 * `user_id` est **nullable en base et toujours rempli par l'API** : les
 * chauffeurs enregistrés avant ce changement n'ont pas de compte, et leur en
 * fabriquer un d'office créerait des identifiants que personne n'a demandés.
 * Toute création passe désormais par `CreateDriverAccount`, qui le remplit.
 *
 * `unique(organization_id, user_id)` : un même compte ne peut pas être deux
 * chauffeurs dans la même organisation. Il peut l'être dans deux organisations
 * différentes — c'est le cas d'un intérimaire.
 *
 * L'unicité du code passe du fournisseur à l'organisation, pour la même raison
 * que sur les véhicules : deux `NULL` sont distincts pour MySQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table): void {
            $table->char('user_id', 26)->nullable()->after('provider_id');
            $table->char('provider_id', 26)->nullable()->change();

            $table->dropUnique(['provider_id', 'code']);
            $table->unique(['organization_id', 'code']);
            $table->unique(['organization_id', 'user_id']);

            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        $orphans = DB::table('drivers')->whereNull('provider_id')->count();

        if ($orphans > 0) {
            throw new RuntimeException(
                "Retour arrière impossible : {$orphans} chauffeur(s) sans fournisseur. Rattachez-les d'abord.",
            );
        }

        Schema::table('drivers', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
            $table->dropUnique(['organization_id', 'user_id']);
            $table->dropUnique(['organization_id', 'code']);
            $table->dropColumn('user_id');
            $table->char('provider_id', 26)->nullable(false)->change();
            $table->unique(['provider_id', 'code']);
        });
    }
};
