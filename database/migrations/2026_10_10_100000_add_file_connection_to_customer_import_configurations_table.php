<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La connexion FTP/SFTP d'une relève automatique, portée par sa configuration
 * d'import (décision Phase 6 levée le 2026-10-10).
 *
 * Jusqu'ici l'exploitant la déclarait dans `.env`
 * (`CUSTOMER_INTEGRATIONS_INBOUND_SOURCES`) : chaque nouveau client demandait
 * une intervention sur le serveur. Elle se règle désormais depuis le
 * back-office, comme la connexion REST d'un pull.
 *
 * - `connection` : hôte, port, utilisateur, répertoire, motif, sort du
 *   fichier après import, auteur — rien de secret ;
 * - `encrypted_password` : chiffré par `Secret` (clé `APP_KEY`), jamais rendu
 *   par l'API, comme `customer_export_configurations.encrypted_password`.
 *
 * Nullable, sans valeur : aucune configuration existante n'est touchée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_import_configurations', function (Blueprint $table): void {
            $table->json('connection')->nullable()->after('validation_rules');
            $table->text('encrypted_password')->nullable()->after('connection');
        });
    }

    public function down(): void
    {
        Schema::table('customer_import_configurations', function (Blueprint $table): void {
            $table->dropColumn(['connection', 'encrypted_password']);
        });
    }
};
