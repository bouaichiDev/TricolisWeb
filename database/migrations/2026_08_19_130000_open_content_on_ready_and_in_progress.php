<?php

use App\Shared\Database\MorphMap;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ouvre le contenu des commandes prêtes et en cours.
 *
 * Décision d'exploitation : il arrive qu'un colis s'ajoute au dernier moment ou
 * qu'un article se rectifie sur le terrain, après que la commande soit passée
 * en « Prête » ou en « En cours ». Les figer dès « Confirmée » obligeait à
 * repasser la commande en arrière, ce que la machine à états n'autorise pas
 * toujours.
 *
 * `PLANNED` et au-delà restent fermés : une tournée construite sur la commande
 * ne serait pas prévenue du changement.
 *
 * Le réglage reste modifiable depuis l'écran des statuts ; cette migration ne
 * fait que porter la décision sur les bases déjà installées, une fois.
 */
return new class extends Migration
{
    /** Statuts dont le contenu s'ouvre. */
    private const array CODES = ['ready', 'in_progress'];

    public function up(): void
    {
        DB::table('statuses')
            ->where('source', MorphMap::ORDER)
            ->whereIn('code', self::CODES)
            ->update(['allows_content_changes' => true]);
    }

    public function down(): void
    {
        DB::table('statuses')
            ->where('source', MorphMap::ORDER)
            ->whereIn('code', self::CODES)
            ->update(['allows_content_changes' => false]);
    }
};
