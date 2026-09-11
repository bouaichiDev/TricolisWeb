<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le statut qu'une entité reçoit à sa création.
 *
 * Il était écrit dans le code et dans les valeurs par défaut des colonnes —
 * `draft` pour une commande, `active` pour une ligne, `pending` pour un colis
 * traité par un service — si bien que l'administrateur, qui dessine pourtant le
 * cycle de vie dans ce référentiel, ne pouvait pas en choisir le point de départ.
 *
 * **Une case sur le statut, et non une table de réglages.** Le référentiel
 * connaît déjà toutes les entités qui portent un statut — `source` — et une
 * entité ajoutée demain y apparaît sans rien écrire. Une table à part
 * dupliquerait cette liste, et divergerait.
 *
 * **Un seul par entité**, garanti à l'écriture par `SetDefaultStatus` : MySQL
 * n'offre pas d'index unique partiel, et un index sur (`source`, `is_default`)
 * interdirait d'avoir plus d'un statut qui ne l'est pas.
 *
 * Aucun statut n'est coché ici : sans choix, la colonne de l'entité garde sa
 * valeur par défaut, et rien ne change pour les données existantes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('statuses', function (Blueprint $table): void {
            $table->boolean('is_default')->default(false)->after('requires_reason');
            $table->index(['source', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::table('statuses', function (Blueprint $table): void {
            $table->dropIndex(['source', 'is_default']);
            $table->dropColumn('is_default');
        });
    }
};
