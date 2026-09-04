<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Groupes de menu créés par une organisation.
 *
 * **Pourquoi ceux-là peuvent vivre en base alors que le catalogue reste en
 * code :** un groupe n'ouvre rien. Il n'a ni route, ni permission — c'est un
 * titre repliable et une icône, au-dessus d'entrées qui, elles, gardent leur
 * destination du catalogue. Il ne peut donc pas mener à « Page introuvable »
 * ni ouvrir un écran interdit, les deux raisons qui gardent le reste en code.
 * Créer un groupe est du rangement, pas du routage.
 *
 * `code` est opaque et immuable — un ULID préfixé — plutôt qu'un slug tiré du
 * nom. Les entrées rangées dedans le désignent par ce code : le dériver du nom
 * ferait perdre tous ses enfants au premier renommage.
 *
 * Le préfixe `grp-` garantit qu'aucun code créé ici ne peut se confondre avec
 * un code du catalogue, présent ou futur. Les deux espaces de noms se croisent
 * partout — `parent_code`, `role_menu_items.code` — et une collision ferait
 * régler une entrée pour une autre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_menu_groups', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->string('code', 64);
            $table->string('label', 60);
            $table->string('icon', 64);
            $table->boolean('is_visible')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_menu_groups');
    }
};
