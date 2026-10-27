<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L'historique des exécutions d'import client (décision Phase 6 levée le
 * 2026-10-10).
 *
 * Une ligne par exécution — dépôt de fichier (portail ou back-office), relève
 * REST, fichier relevé en FTP/SFTP — réussie ou non. Avant, seuls l'audit
 * (compteurs) et un résumé en cache de 24 h existaient.
 *
 * `errors` garde les commandes refusées : rang, référence externe, champs et
 * messages — jamais le contenu d'une ligne (nom, adresse, téléphone). La liste
 * est plafonnée par `CustomerImportHistory`.
 *
 * Supprimer une configuration supprime son historique ; supprimer un client
 * est refusé tant qu'il en a, comme pour ses envois (`export_jobs`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_import_runs', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('customer_id', 26);
            $table->char('configuration_id', 26);
            $table->string('channel', 16);
            $table->string('source', 32);
            $table->string('status', 32);
            $table->string('file_name')->nullable();
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('order_count')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('invalid_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->json('errors')->nullable();
            $table->text('error_message')->nullable();
            $table->char('triggered_by', 26)->nullable();
            $table->string('job_id', 64)->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at');

            $table->index(['configuration_id', 'finished_at']);
            $table->index(['customer_id', 'finished_at']);
            $table->index('status');

            $table->foreign('customer_id')->references('id')->on('customers')->restrictOnDelete();
            $table->foreign('configuration_id')->references('id')->on('customer_import_configurations')->cascadeOnDelete();
            $table->foreign('triggered_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_import_runs');
    }
};
