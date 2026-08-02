<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Execution d'un export — donnee historique.
     *
     * `RESTRICT` sur les deux cles etrangeres, la ou les configurations sont en
     * `CASCADE` : un job documente ce qui a ete envoye, il ne disparait pas avec
     * les reglages qui l'ont produit. L'asymetrie est deliberee.
     *
     * `customer_id` est redondant avec `configuration.customer_id`. Le §24
     * interdit de supprimer cette redondance : la valeur est donc **forcee** a
     * celle de la configuration, jamais acceptee en entree.
     *
     * `entity_type` porte un alias de la morph map ; aucune FK sur `entity_id`,
     * qui peut designer plusieurs tables.
     */
    public function up(): void
    {
        Schema::create('export_jobs', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('customer_id', 26);
            $table->char('configuration_id', 26);
            $table->string('entity_type', 64)->nullable();
            $table->char('entity_id', 26)->nullable();
            $table->string('file_name')->nullable();
            $table->string('storage_path')->nullable();
            $table->string('status', 32);
            $table->unsignedInteger('attempt_count')->default(0);
            $table->dateTime('generated_at')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->text('error_message')->nullable();

            $table->index('customer_id');
            $table->index('configuration_id');
            $table->index(['entity_type', 'entity_id']);
            $table->index('status');
            $table->index('generated_at');
            $table->index('sent_at');

            $table->foreign('customer_id')->references('id')->on('customers')->restrictOnDelete();
            $table->foreign('configuration_id')
                ->references('id')->on('customer_export_configurations')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_jobs');
    }
};
