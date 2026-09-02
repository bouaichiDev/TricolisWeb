<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Une etape de parcours ne concerne pas toutes les prestations.
 *
 * Une commande porte souvent un chargement, une livraison et un montage. Le
 * parcours les melangeait toutes : le client final voyait « planifie » trois
 * fois sans savoir de quoi, alors qu'il ne suit que sa livraison. L'etape dit
 * desormais **de quelle prestation elle parle** ; nulle, elle vaut pour toutes,
 * ce qui preserve les parcours deja regles.
 *
 * **`visible_to_customer` separe l'exploitation du client.** Le chargement au
 * depot interesse le planificateur, jamais le destinataire ; sans ce drapeau il
 * faudrait choisir entre le suivre en interne ou le montrer dehors.
 *
 * **`shows_proof_of_delivery` attache la preuve a l'etape qui la produit.** Une
 * preuve offerte des « planifie » n'existe pas encore ; offerte a « livre »,
 * elle repond a la seule question que le client se pose alors.
 *
 * L'unicite gagne la prestation : deux etapes peuvent viser le meme statut si
 * elles ne parlent pas de la meme. MySQL considerant deux NULL comme distincts,
 * l'unicite du cas general — une etape sans prestation — reste tenue par la
 * validation, la ou elle peut nommer le doublon.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tracking_event_definitions', function (Blueprint $table): void {
            $table->char('service_id', 26)->nullable()->after('source_type');
            $table->boolean('visible_to_customer')->default(false)->after('api_configuration_id');
            $table->boolean('shows_proof_of_delivery')->default(false)->after('visible_to_customer');

            $table->dropUnique('ted_source_status_unique');
            $table->unique(
                ['organization_id', 'source_type', 'status_code', 'service_id'],
                'ted_source_status_service_unique',
            );

            $table->foreign('service_id')->references('id')->on('services')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tracking_event_definitions', function (Blueprint $table): void {
            $table->dropForeign(['service_id']);
            $table->dropUnique('ted_source_status_service_unique');
            $table->unique(['organization_id', 'source_type', 'status_code'], 'ted_source_status_unique');
            $table->dropColumn(['service_id', 'visible_to_customer', 'shows_proof_of_delivery']);
        });
    }
};
