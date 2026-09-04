<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Entrées qu'un rôle ne voit pas.
 *
 * Le menu se règle désormais à deux niveaux, et ils ne portent pas la même
 * chose :
 *
 * - **l'organisation** décide du vocabulaire — l'ordre, les noms, les icônes,
 *   le rattachement — et de ce qui n'existe pas chez elle ;
 * - **le rôle** décide seulement de ce que ce métier-là a besoin de voir.
 *
 * Le partage n'est pas arbitraire. Un utilisateur peut cumuler plusieurs rôles :
 * s'ils portaient chacun un ordre et des libellés, il en recevrait deux, et il
 * faudrait inventer un départage que rien ne justifierait. La visibilité, elle,
 * se combine sans ambiguïté — par **union**, comme les permissions déjà : une
 * entrée s'affiche dès qu'un des rôles la montre, et ajouter un rôle n'enlève
 * jamais un écran.
 *
 * L'absence de ligne vaut « visible », comme au niveau de l'organisation : un
 * rôle créé après l'ajout d'une entrée la voit sans migration de données.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_menu_items', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('role_id', 26);
            $table->string('code', 64);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->unique(['role_id', 'code']);
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_menu_items');
    }
};
