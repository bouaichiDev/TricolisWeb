<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L'étape suivie en direct dit *quelle* API la renseigne.
 *
 * `is_live` disait qu'une étape se suivait sur une carte, sans dire d'où venait
 * la position. Deux champs pour une seule idée auraient divergé : une étape
 * marquée vivante sans API, ou l'inverse.
 *
 * La clé étrangère porte les deux : non nulle, l'étape est suivie en direct, et
 * on sait par quoi. `nullOnDelete` — retirer une configuration d'API ne doit pas
 * effacer l'étape du parcours, seulement son suivi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tracking_event_definitions', function (Blueprint $table): void {
            $table->char('api_configuration_id', 26)->nullable()->after('icon');

            $table->foreign('api_configuration_id')
                ->references('id')->on('organization_api_configurations')
                ->nullOnDelete();
        });

        Schema::table('tracking_event_definitions', function (Blueprint $table): void {
            $table->dropColumn('is_live');
        });
    }

    public function down(): void
    {
        Schema::table('tracking_event_definitions', function (Blueprint $table): void {
            $table->boolean('is_live')->default(false)->after('icon');
            $table->dropForeign(['api_configuration_id']);
            $table->dropColumn('api_configuration_id');
        });
    }
};
