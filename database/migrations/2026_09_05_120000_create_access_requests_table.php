<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les demandes d'accès déposées depuis l'écran de connexion.
 *
 * **Une demande n'est pas une organisation.** Le formulaire public existait
 * déjà — `POST /auth/register` créait sur-le-champ l'organisation, son compte
 * propriétaire et son jeton — et c'est précisément ce qu'on ne veut plus :
 * n'importe qui obtenait un back-office en trois champs. Ici la demande est
 * déposée, puis **la plateforme décide**. Tant qu'elle n'a pas décidé, rien
 * n'existe : ni compte, ni organisation, ni jeton.
 *
 * Le demandeur laisse de quoi être rappelé — nom, adresse, téléphone — parce
 * que c'est ce que fait un administrateur avant d'accepter : il vérifie à qui
 * il ouvre la porte, et le téléphone est le seul canal qui le lui permette
 * hors du courriel qu'il s'apprête à croire sur parole.
 *
 * `organization_id` et `user_id` restent vides jusqu'à l'acceptation : ils
 * portent ce que la décision a **produit**, et les remplir d'avance
 * reviendrait à créer ce que la demande n'a pas encore obtenu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_requests', function (Blueprint $table) {
            $table->char('id', 26)->primary();

            $table->string('company_name');
            $table->string('contact_name');
            $table->string('email');
            $table->string('phone', 40);
            $table->text('message')->nullable();

            $table->string('status', 20)->default('pending');
            $table->text('decision_note')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->char('decided_by', 26)->nullable();

            // Ce que l'acceptation a produit, et rien avant elle.
            $table->char('organization_id', 26)->nullable();
            $table->char('user_id', 26)->nullable();

            $table->timestamps();

            $table->foreign('decided_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('organization_id')->references('id')->on('organizations')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();

            // L'écran de la plateforme s'ouvre sur les demandes en attente, les
            // plus anciennes d'abord : personne n'attend plus longtemps qu'un
            // autre parce que la liste est arrivée dans le désordre.
            $table->index(['status', 'created_at']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_requests');
    }
};
