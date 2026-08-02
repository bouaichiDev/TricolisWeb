<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Règle d'envoi : quel template, sur quel événement, vers quel rôle.
     *
     * `template_id` est NOT NULL — cardinalité `CommunicationTemplate "1"` — et
     * en RESTRICT, doublé d'un refus métier : supprimer un template utilisé par
     * une règle est refusé en 409 avant d'atteindre la contrainte.
     *
     * `delay_unit` reste une chaîne : le §17 interdit d'en faire un enum. Les
     * unités acceptées sont validées côté Form Request, contre celles que
     * `CarbonInterval` sait ajouter.
     *
     * `conditions` est un JSON facultatif : `null` signifie règle
     * inconditionnelle.
     */
    public function up(): void
    {
        Schema::create('communication_rules', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->char('service_id', 26)->nullable();
            $table->char('template_id', 26);
            $table->string('event_type', 32);
            $table->string('recipient_role', 32);
            $table->integer('delay_value')->default(0);
            $table->string('delay_unit', 16);
            $table->json('conditions')->nullable();
            $table->boolean('is_automatic')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('organization_id');
            $table->index('service_id');
            $table->index('template_id');
            $table->index('event_type');
            $table->index('recipient_role');
            $table->index('is_automatic');
            $table->index('is_active');
            $table->index('created_at');

            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign('service_id')->references('id')->on('services')->restrictOnDelete();
            $table->foreign('template_id')->references('id')->on('communication_templates')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_rules');
    }
};
