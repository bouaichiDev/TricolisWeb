<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un véhicule peut appartenir au transporteur lui-même.
 *
 * `provider_id` était obligatoire : toute la flotte devait passer par un
 * fournisseur, alors qu'un transporteur possède ses propres camions.
 *
 * **`organization_id` devient une colonne à part entière.** L'organisation d'un
 * véhicule se lisait jusqu'ici à travers son fournisseur ; sans fournisseur,
 * elle n'aurait plus rien pour s'ancrer, et le cloisonnement reposerait sur une
 * jointure devenue facultative. La colonne est remplie depuis le fournisseur
 * existant avant d'être rendue obligatoire.
 *
 * L'unicité du code passe du fournisseur à l'organisation : MySQL considère
 * deux `NULL` comme distincts, et `unique(provider_id, code)` aurait laissé
 * autant de doublons que de véhicules sans fournisseur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table): void {
            $table->char('organization_id', 26)->nullable()->after('id');
        });

        DB::statement('UPDATE vehicles v JOIN providers p ON p.id = v.provider_id SET v.organization_id = p.organization_id');

        Schema::table('vehicles', function (Blueprint $table): void {
            $table->char('organization_id', 26)->nullable(false)->change();
            $table->char('provider_id', 26)->nullable()->change();

            $table->dropUnique(['provider_id', 'code']);
            $table->unique(['organization_id', 'code']);
            $table->index('organization_id');

            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        // Les véhicules sans fournisseur n'ont pas de place dans l'ancien
        // schéma : les supprimer effacerait des données réelles, on refuse donc
        // plutôt de revenir en arrière tant qu'il en existe.
        $orphans = DB::table('vehicles')->whereNull('provider_id')->count();

        if ($orphans > 0) {
            throw new RuntimeException(
                "Retour arrière impossible : {$orphans} véhicule(s) sans fournisseur. Rattachez-les d'abord.",
            );
        }

        Schema::table('vehicles', function (Blueprint $table): void {
            $table->dropForeign(['organization_id']);
            $table->dropUnique(['organization_id', 'code']);
            $table->dropIndex(['organization_id']);
            $table->char('provider_id', 26)->nullable(false)->change();
            $table->unique(['provider_id', 'code']);
            $table->dropColumn('organization_id');
        });
    }
};
