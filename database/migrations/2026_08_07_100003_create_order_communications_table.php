<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Communication rattachée à une commande — donnée historique.
     *
     * `subject`, `body`, `template_variables`, `recipient_*` sont des
     * **snapshots** : ils conservent ce qui a réellement été envoyé. Modifier
     * ensuite le template ou la règle ne les touche pas.
     *
     * D'où `SET NULL` sur `template_id` et `communication_rule_id` : le contenu
     * étant figé, perdre le lien ne perd aucune information. Le refus métier de
     * suppression arrive de toute façon avant, à l'API.
     *
     * `recipient_email` et `recipient_phone` sont tous deux nullables : le §20
     * interdit d'exiger l'un pour un canal qui utilise l'autre. La contrainte
     * est portée par la validation, selon le canal.
     *
     * Les six horodatages d'état sont nullables et sans défaut : une date posée
     * d'avance affirmerait un événement qui n'a pas eu lieu.
     */
    public function up(): void
    {
        Schema::create('order_communications', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->char('order_id', 26);
            $table->char('template_id', 26)->nullable();
            $table->char('communication_rule_id', 26)->nullable();
            $table->string('channel', 32);
            $table->string('communication_type', 32);
            $table->string('recipient_role', 32);
            $table->string('recipient_name');
            $table->string('recipient_email')->nullable();
            $table->string('recipient_phone', 32)->nullable();
            $table->text('subject')->nullable();
            $table->longText('body');
            $table->json('template_variables')->nullable();
            $table->string('status', 16);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->json('provider_response')->nullable();
            $table->text('error_message')->nullable();
            $table->char('created_by', 26)->nullable();
            $table->timestamps();

            $table->index('organization_id');
            $table->index('order_id');
            $table->index('template_id');
            $table->index('communication_rule_id');
            $table->index('channel');
            $table->index('communication_type');
            $table->index('recipient_role');
            $table->index('status');
            $table->index('scheduled_at');
            $table->index('queued_at');
            $table->index('sent_at');
            $table->index('delivered_at');
            $table->index('read_at');
            $table->index('failed_at');
            $table->index('provider_message_id');
            $table->index('created_by');
            $table->index('created_at');

            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign('order_id')->references('id')->on('orders')->restrictOnDelete();
            $table->foreign('template_id')->references('id')->on('communication_templates')->nullOnDelete();
            $table->foreign('communication_rule_id')->references('id')->on('communication_rules')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_communications');
    }
};
