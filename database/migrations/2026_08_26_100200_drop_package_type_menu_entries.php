<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Retire les entrées de menu des référentiels fusionnés.
 *
 * `SyncOrganizationMenu` crée les lignes manquantes et ne touche jamais aux
 * existantes — c'est ce qui permet à une organisation de masquer ce qu'elle
 * veut sans être écrasée à la synchronisation suivante. La contrepartie est
 * qu'une entrée retirée du catalogue y survit : « Types de colis » et « Types
 * de regroupement » mèneraient vers des écrans qui n'existent plus.
 *
 * Le retour arrière ne les recrée pas : la synchronisation s'en charge dès que
 * le catalogue les redéclare.
 */
return new class extends Migration
{
    private const array REMOVED = ['package-types', 'grouping-types'];

    public function up(): void
    {
        DB::table('organization_menu_items')->whereIn('code', self::REMOVED)->delete();
    }

    public function down(): void
    {
        // Rien à défaire : `tricolis:sync-organization-menus` recrée ce que le
        // catalogue déclare.
    }
};
