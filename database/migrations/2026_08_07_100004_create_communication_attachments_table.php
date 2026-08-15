<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pièce jointe d'une communication.
     *
     * `communication_id` est en CASCADE : le diagramme pose une composition
     * (`OrderCommunication "1" *-- "0..*" CommunicationAttachment`). La
     * suppression d'une communication — possible seulement en brouillon —
     * emporte ses liaisons.
     *
     * `document_id` est en RESTRICT : la pièce jointe pointe un document, elle
     * ne le possède pas. Supprimer le document sous une communication envoyée
     * la rendrait mensongère.
     *
     * Le diagramme ne donne que `createdAt`, sans `updatedAt` : les timestamps
     * Eloquent sont désactivés et `created_at` est posé à la création, comme
     * pour `DocumentLink` en Phase 2.
     *
     * Unique `(communication_id, document_id)` : joindre deux fois le même
     * document au même message n'a pas de sens et produirait deux pièces
     * identiques chez le destinataire.
     */
    public function up(): void
    {
        Schema::create('communication_attachments', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('communication_id', 26);
            $table->char('document_id', 26);
            $table->string('file_name_snapshot');
            $table->string('mime_type_snapshot', 128);
            $table->timestamp('created_at')->nullable();

            $table->unique(['communication_id', 'document_id']);
            $table->index('communication_id');
            $table->index('document_id');
            $table->index('created_at');

            $table->foreign('communication_id')->references('id')->on('order_communications')->cascadeOnDelete();
            $table->foreign('document_id')->references('id')->on('documents')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_attachments');
    }
};
