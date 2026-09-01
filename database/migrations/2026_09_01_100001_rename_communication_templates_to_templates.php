<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Une seule table de modèles pour toute la plateforme.
 *
 * Un modèle de facture et un modèle d'e-mail sont la même chose : un texte à
 * trous, résolu puis rendu. Les séparer en deux tables aurait imposé deux
 * moteurs de rendu, deux écrans, deux jeux de permissions — et la première
 * divergence entre les deux serait passée inaperçue.
 *
 * **Un renommage, pas une recréation.** `RENAME TABLE` conserve les
 * identifiants, et MySQL réoriente de lui-même les clés étrangères qui visaient
 * `communication_templates` : `communication_rules.template_id` et
 * `order_communications.template_id` continuent de désigner les mêmes lignes.
 * Aucune communication historique n'est perdue, aucun doublon n'est créé.
 *
 * `customer_id` nul désigne le modèle global du transporteur ; renseigné, il
 * désigne un modèle propre à un client. C'est ce qui permet le repli
 * « client → global » sans jamais servir le modèle d'un autre client.
 *
 * `channel` devient facultatif : une facture est un document, pas un message.
 * Lui inventer un canal `email` mentirait sur sa nature et la ferait apparaître
 * dans les filtres de messagerie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('communication_templates', 'templates');

        Schema::table('templates', function (Blueprint $table): void {
            $table->char('customer_id', 26)->nullable()->after('organization_id');
            $table->string('channel', 32)->nullable()->change();

            $table->index('customer_id');
            $table->foreign('customer_id')->references('id')->on('customers')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table): void {
            $table->dropForeign(['customer_id']);
            $table->dropIndex(['customer_id']);
            $table->dropColumn('customer_id');
        });

        // Les modèles sans canal n'ont pas de place dans une table de modèles
        // de message : le retour arrière les rattache au canal e-mail plutôt
        // que d'échouer sur la contrainte NOT NULL. La valeur est posée avant
        // la contrainte — l'inverse échouerait en mode strict.
        DB::table('templates')->whereNull('channel')->update(['channel' => 'email']);

        Schema::table('templates', function (Blueprint $table): void {
            $table->string('channel', 32)->nullable(false)->change();
        });

        Schema::rename('templates', 'communication_templates');
    }
};
