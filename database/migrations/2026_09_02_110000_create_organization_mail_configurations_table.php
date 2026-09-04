<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La boite d'envoi de chaque organisation.
 *
 * Tout partait jusqu'ici du mailer unique du projet : deux organisations
 * hebergees sur la meme installation envoyaient leurs factures depuis la meme
 * adresse. Le client d'Atlas recevait un courrier signe Tricolis, et un
 * transporteur ne peut pas se presenter chez ses clients sous le nom d'un
 * autre.
 *
 * **Une seule configuration par organisation** — d'ou la contrainte unique.
 * C'est une identite d'expedition, pas une liste de serveurs : en avoir deux
 * poserait la question de savoir lequel repond, sans qu'aucune reponse ne soit
 * meilleure.
 *
 * Le mot de passe n'est pas ici : il vit dans `encrypted_password`, chiffre a
 * l'ecriture, jamais rendu par l'API.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_mail_configurations', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);

            $table->string('host');
            $table->unsignedSmallInteger('port')->default(587);
            // `tls`, `ssl`, ou nul pour aucun chiffrement. Une chaine plutot
            // qu'un booleen : les serveurs distinguent les deux, et un port 465
            // en `tls` ne se connecte pas.
            $table->string('encryption', 16)->nullable();
            $table->string('username')->nullable();
            $table->text('encrypted_password')->nullable();

            // L'identite affichee, distincte de l'authentification : on
            // s'authentifie souvent avec un compte technique et l'on signe avec
            // l'adresse du service client.
            $table->string('from_address');
            $table->string('from_name')->nullable();
            $table->string('reply_to')->nullable();

            $table->boolean('is_active')->default(true);
            $table->dateTime('last_used_at')->nullable();
            $table->timestamps();

            $table->unique('organization_id');

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_mail_configurations');
    }
};
