<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pose la cle etrangere laissee en attente par la Phase 2.
     *
     * `2026_08_01_100005_create_packages_table` cree bien
     * `current_stock_location_id` — le diagramme la declare — mais sans
     * contrainte, avec ce commentaire : « `stock_locations` releve d'une phase
     * ulterieure. Aucune contrainte n'est posee tant que la table n'existe
     * pas. »
     *
     * Elle existe maintenant. La correction est **additive** : la migration de
     * la Phase 2 n'est pas modifiee, elle est peut-etre deja executee ailleurs.
     *
     * `SET NULL` : supprimer un emplacement ne supprime pas les colis qui s'y
     * trouvaient, il les delocalise.
     */
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table): void {
            $table->foreign('current_stock_location_id')
                ->references('id')
                ->on('stock_locations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table): void {
            $table->dropForeign(['current_stock_location_id']);
        });
    }
};
