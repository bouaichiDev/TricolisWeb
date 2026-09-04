<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les API externes qu'un organisme appelle.
 *
 * À ne pas confondre avec `customer_api_configurations`, qui va dans l'autre
 * sens : là, un client détient une clé pour **nous** appeler. Ici, c'est nous
 * qui appelons — la position d'un chauffeur, par exemple, rendue par le
 * système de télématique de l'organisme.
 *
 * Le secret est **chiffré** et n'est jamais renvoyé par l'API : la ressource
 * expose `hasCredentials`, un booléen, et rien de plus. Un secret qui traverse
 * une réponse JSON finit dans un journal, un cache de navigateur ou une capture
 * d'écran.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_api_configurations', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);

            // Ce a quoi l'API sert, cote metier : driver_position, weather…
            // Une chaine et non une enumeration : les usages viendront.
            $table->string('code', 64);
            $table->string('name');
            $table->string('base_url', 512);

            $table->string('auth_type', 32)->default('none');
            $table->text('encrypted_credentials')->nullable();
            $table->json('headers')->nullable();
            $table->unsignedSmallInteger('timeout_seconds')->default(10);

            $table->boolean('is_active')->default(true);
            $table->dateTime('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'is_active']);

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_api_configurations');
    }
};
