<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Valeurs par défaut des agrégats et montants d'un service.
     *
     * Ces colonnes étaient `NOT NULL` sans défaut : créer un service sans
     * préciser son poids ou son prix produisait une erreur SQL brute au lieu
     * d'un comportement métier. Zéro est la valeur neutre attendue — un service
     * sans colis pèse zéro, un service non tarifé coûte zéro tant que le moteur
     * de tarification n'existe pas.
     */
    public function up(): void
    {
        Schema::table('order_services', function (Blueprint $table): void {
            $table->decimal('weight', 12, 3)->default(0)->change();
            $table->decimal('volume', 12, 4)->default(0)->change();
            $table->unsignedInteger('package_count')->default(0)->change();
            $table->unsignedInteger('remaining_time_minutes')->default(0)->change();
            $table->decimal('customer_unit_price', 12, 2)->default(0)->change();
            $table->decimal('customer_total_price', 12, 2)->default(0)->change();
            $table->decimal('provider_unit_cost', 12, 2)->default(0)->change();
            $table->decimal('provider_total_cost', 12, 2)->default(0)->change();
            $table->string('status', 32)->default('draft')->change();
        });
    }

    public function down(): void
    {
        Schema::table('order_services', function (Blueprint $table): void {
            $table->decimal('weight', 12, 3)->change();
            $table->decimal('volume', 12, 4)->change();
            $table->unsignedInteger('package_count')->change();
            $table->unsignedInteger('remaining_time_minutes')->change();
            $table->decimal('customer_unit_price', 12, 2)->change();
            $table->decimal('customer_total_price', 12, 2)->change();
            $table->decimal('provider_unit_cost', 12, 2)->change();
            $table->decimal('provider_total_cost', 12, 2)->change();
            $table->string('status', 32)->change();
        });
    }
};
