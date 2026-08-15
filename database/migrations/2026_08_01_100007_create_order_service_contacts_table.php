<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_service_contacts', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('order_service_id', 26);
            // Le contact partagé peut disparaître : les colonnes de snapshot
            // suffisent alors à reconstituer l'historique de la commande.
            $table->char('contact_id', 26)->nullable();
            $table->string('contact_role', 32)->default('other');
            $table->string('first_name_snapshot')->nullable();
            $table->string('last_name_snapshot')->nullable();
            $table->string('phone_snapshot')->nullable();
            $table->string('mobile_snapshot')->nullable();
            $table->string('email_snapshot')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['order_service_id', 'contact_role']);
            $table->index('contact_id');

            $table->foreign('order_service_id')->references('id')->on('order_services')->cascadeOnDelete();
            $table->foreign('contact_id')->references('id')->on('contacts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_service_contacts');
    }
};
