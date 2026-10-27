<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index `orders(customer_id, external_reference)` (décision Phase 6 levée le
 * 2026-10-10).
 *
 * Chaque commande importée est d'abord cherchée par sa référence externe chez
 * son client (`ImportCustomerOrderAction`, `ImportPipeline`) : sans index, chaque recherche
 * parcourait toutes les commandes du client — un import de 5000 lignes en
 * faisait autant.
 *
 * Index simple, **pas d'unicité** : l'idempotence reste applicative (verrou +
 * recherche), et des commandes saisies à la main peuvent légitimement partager
 * une référence. Aucune donnée n'est modifiée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->index(['customer_id', 'external_reference'], 'orders_customer_external_reference_index');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('orders_customer_external_reference_index');
        });
    }
};
