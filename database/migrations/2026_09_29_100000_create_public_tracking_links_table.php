<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Liens publics de suivi d'une commande (portail client, Phase 3).
 *
 * Aucun mécanisme de partage générique n'existait : les invitations et les
 * clés d'API ont chacune leur table, liée à un autre usage. Celle-ci reprend
 * leur principe — **seule l'empreinte SHA-256 du jeton est stockée** — pour
 * qu'une fuite de la table ne donne aucun lien utilisable.
 *
 * Une ligne par commande (`Order "1" -- "0..1" PublicTrackingLink`) : régénérer
 * remplace l'empreinte, ce qui rend l'ancien lien inutilisable sur-le-champ.
 * La révocation ne supprime rien — `revoked_at` garde la trace.
 *
 * `access_count` et `last_access_at` remplacent l'audit pour les consultations
 * publiques : un rafraîchissement toutes les trente secondes n'a rien à faire
 * dans le journal principal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_tracking_links', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('order_id', 26)->unique();
            $table->char('token_hash', 64)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->char('created_by', 26)->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_access_at')->nullable();
            $table->unsignedInteger('access_count')->default(0);
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_tracking_links');
    }
};
