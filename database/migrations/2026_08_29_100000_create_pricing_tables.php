<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le domaine tarifaire, tel que le diagramme mis à jour le décrit.
 *
 * **Une liste, des règles, éventuellement une matrice.** La formule est
 * obligatoire et porte le calcul ; la matrice ne calcule rien — elle choisit
 * quelle règle appliquer selon une dimension, aujourd'hui le code postal.
 *
 * `is_active` plutôt qu'un `status` : ces tables n'ont pas de cycle de vie
 * métier, seulement une existence active ou non. En ajouter un obligerait à le
 * décrire au référentiel sans qu'aucune transition ne l'anime.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_lists', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->string('code', 64);
            $table->string('name', 255);
            // `global` ou `customer` : c'est cette portee qui decide de la
            // priorite a la resolution, le client passant avant le global.
            $table->string('scope', 16)->default('global');
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'scope', 'is_active']);

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });

        // Une liste client peut servir plusieurs clients : la table de liaison
        // evite de dupliquer les memes regles pour chacun.
        Schema::create('customer_price_lists', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('price_list_id', 26);
            $table->char('customer_id', 26);
            $table->timestamps();

            $table->unique(['price_list_id', 'customer_id']);
            $table->index('customer_id');

            $table->foreign('price_list_id')->references('id')->on('price_lists')->cascadeOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
        });

        Schema::create('price_rules', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('price_list_id', 26);
            // Nul : la regle vaut pour toute prestation que ses conditions
            // acceptent. Renseigne : elle ne vaut que pour ce service.
            $table->char('service_id', 26)->nullable();
            $table->string('code', 64);
            $table->string('name', 255);
            // La formule est obligatoire : une regle sans formule ne calcule
            // rien, et la matrice ne la remplace pas.
            $table->text('formula');
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['price_list_id', 'code']);
            $table->index(['price_list_id', 'service_id', 'is_active']);

            $table->foreign('price_list_id')->references('id')->on('price_lists')->cascadeOnDelete();
            $table->foreign('service_id')->references('id')->on('services')->nullOnDelete();
        });

        Schema::create('price_rule_conditions', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('price_rule_id', 26);
            $table->string('variable', 64);
            $table->string('operator', 16);
            // Deux bornes plutot qu'une valeur : `between` en a besoin, et les
            // autres operateurs n'utilisent que la premiere.
            $table->string('value_from', 255)->nullable();
            $table->string('value_to', 255)->nullable();
            $table->timestamps();

            $table->index('price_rule_id');

            $table->foreign('price_rule_id')->references('id')->on('price_rules')->cascadeOnDelete();
        });

        Schema::create('price_matrices', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('price_list_id', 26);
            $table->char('service_id', 26)->nullable();
            $table->string('code', 64);
            $table->string('name', 255);
            // La dimension lue sur la prestation : `postal_code` aujourd'hui.
            $table->string('dimension', 32)->default('postal_code');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['price_list_id', 'code']);
            $table->index(['price_list_id', 'service_id', 'is_active']);

            $table->foreign('price_list_id')->references('id')->on('price_lists')->cascadeOnDelete();
            $table->foreign('service_id')->references('id')->on('services')->nullOnDelete();
        });

        Schema::create('price_matrix_rows', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('price_matrix_id', 26);
            $table->char('price_rule_id', 26);
            $table->string('label', 255);
            // Un code postal n'est pas partout un entier : `numeric` compare
            // des bornes, `prefix` et `exact` gardent zeros de tete et lettres.
            $table->string('match_mode', 16)->default('numeric');
            $table->string('range_from', 32);
            $table->string('range_to', 32)->nullable();
            $table->unsignedInteger('priority')->default(100);
            $table->timestamps();

            $table->index(['price_matrix_id', 'priority']);

            $table->foreign('price_matrix_id')->references('id')->on('price_matrices')->cascadeOnDelete();
            $table->foreign('price_rule_id')->references('id')->on('price_rules')->cascadeOnDelete();
        });

        // L'historique du calcul : il explique un prix apres coup, quand la
        // formule a change depuis.
        Schema::create('pricing_calculations', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->char('order_service_id', 26);
            $table->char('customer_id', 26);
            $table->char('price_list_id', 26)->nullable();
            $table->char('price_rule_id', 26)->nullable();
            $table->char('price_matrix_id', 26)->nullable();
            $table->char('price_matrix_row_id', 26)->nullable();
            $table->string('scope', 16);
            $table->string('service_code', 64)->nullable();
            $table->text('formula_snapshot');
            $table->json('variables_snapshot');
            $table->decimal('result', 12, 2);
            $table->string('currency_code', 3);
            $table->timestamp('calculated_at');
            $table->timestamps();

            $table->index(['organization_id', 'order_service_id']);
            $table->index('customer_id');

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('order_service_id')->references('id')->on('order_services')->cascadeOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
            // Les references tarifaires sont mises a nul, jamais cascadees :
            // supprimer une regle ne doit pas effacer l'explication d'un prix
            // deja facture.
            $table->foreign('price_list_id')->references('id')->on('price_lists')->nullOnDelete();
            $table->foreign('price_rule_id')->references('id')->on('price_rules')->nullOnDelete();
            $table->foreign('price_matrix_id')->references('id')->on('price_matrices')->nullOnDelete();
            $table->foreign('price_matrix_row_id')->references('id')->on('price_matrix_rows')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_calculations');
        Schema::dropIfExists('price_matrix_rows');
        Schema::dropIfExists('price_matrices');
        Schema::dropIfExists('price_rule_conditions');
        Schema::dropIfExists('price_rules');
        Schema::dropIfExists('customer_price_lists');
        Schema::dropIfExists('price_lists');
    }
};
