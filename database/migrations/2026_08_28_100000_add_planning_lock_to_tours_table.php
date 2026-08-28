<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La réservation d'une tournée pendant sa planification sur carte.
 *
 * Le §23 interdit d'ajouter `lockedBy` et `lockedAt` **« sans validation de
 * conception »**. Cette validation est venue le 28 août 2026 : le propriétaire
 * du projet exige qu'une tournée reste réservée à celui qui la compose, et que
 * confirmer ses modifications **ne touche pas au statut**.
 *
 * Cette dernière contrainte est ce qui impose ces colonnes. Tant que la fin de
 * l'exclusivité coïncidait avec la sortie du brouillon, elle se déduisait du
 * statut ; puisqu'une tournée confirmée dans la carte doit rester au brouillon,
 * plus rien dans les données ne dirait que le planificateur a rendu la main.
 *
 * **Ce n'est pas une table `PlanningLock`**, que le §20 interdit : deux colonnes
 * sur la tournée elle-même, qui disparaissent avec elle.
 *
 * `locked_at` n'est pas décoratif : il permettra de reprendre une réservation
 * oubliée sans inventer de « force unlock », que le §121 refuse pour cette phase.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table): void {
            $table->char('locked_by', 26)->nullable()->after('status');
            $table->dateTime('locked_at')->nullable()->after('locked_by');

            $table->index('locked_by');

            // Le compte part, la reservation tombe : une tournee retenue par un
            // utilisateur supprime resterait bloquee pour tout le monde.
            $table->foreign('locked_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table): void {
            $table->dropForeign(['locked_by']);
            $table->dropIndex(['locked_by']);
            $table->dropColumn(['locked_by', 'locked_at']);
        });
    }
};
