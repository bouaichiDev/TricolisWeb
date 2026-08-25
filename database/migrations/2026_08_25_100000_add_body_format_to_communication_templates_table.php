<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Format du corps d'un modèle de message.
 *
 * Un e-mail se rédige souvent en HTML — gras, listes, lien de suivi — quand un
 * SMS ne peut être que du texte. Sans cette colonne, le serveur ne savait pas
 * s'il devait échapper le corps ou l'envoyer tel quel : les deux se stockaient
 * dans le même `longText`, et le destinataire recevait des balises en clair ou
 * un texte sans mise en forme, selon le hasard de l'implémentation d'envoi.
 *
 * `text` par défaut : c'est le comportement d'avant, et une migration ne doit
 * pas changer le rendu des modèles déjà écrits.
 *
 * Volontairement une chaîne courte et non une énumération SQL : le projet
 * n'utilise pas d'enum en base, et les valeurs sont bornées côté requête.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communication_templates', function (Blueprint $table): void {
            $table->string('body_format', 16)->default('text')->after('body_template');
        });
    }

    public function down(): void
    {
        Schema::table('communication_templates', function (Blueprint $table): void {
            $table->dropColumn('body_format');
        });
    }
};
