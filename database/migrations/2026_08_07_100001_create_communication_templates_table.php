<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Modèle de message, par canal et par nature métier.
     *
     * `subject_template` est nullable : un SMS n'a pas d'objet, et le §11
     * interdit de l'exiger pour SMS ou WhatsApp. La validation ne le rend
     * obligatoire que pour le canal EMAIL.
     *
     * `service_id` est nullable — cardinalité `Service "0..1"` — et en RESTRICT :
     * supprimer un service ne doit pas vider silencieusement le périmètre d'un
     * template, l'association du diagramme n'étant pas une composition.
     *
     * `channel` et `template_type` sont adossés aux enums du diagramme mais
     * stockés en VARCHAR : convention des Phases 2, 4 et 6.
     */
    public function up(): void
    {
        Schema::create('communication_templates', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->char('service_id', 26)->nullable();
            $table->string('code', 64);
            $table->string('name');
            $table->string('channel', 32);
            $table->string('template_type', 32);
            $table->text('subject_template')->nullable();
            $table->longText('body_template');
            $table->string('language', 10);
            $table->json('available_variables')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
            $table->index('organization_id');
            $table->index('service_id');
            $table->index('channel');
            $table->index('template_type');
            $table->index('language');
            $table->index('is_default');
            $table->index('is_active');
            $table->index('created_at');

            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign('service_id')->references('id')->on('services')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_templates');
    }
};
