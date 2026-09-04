<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Le menu descend de l'organisation vers le rôle.
 *
 * Il se réglait à deux niveaux : l'organisation pour l'ordre, les noms, les
 * icônes et les groupes ; le rôle pour la seule visibilité. Deux écrans, donc,
 * et il fallait savoir lequel ouvrir pour obtenir quoi. **Le réglage tient
 * désormais en un seul endroit — la fiche du rôle — et chaque rôle porte son
 * menu entier.**
 *
 * Ce que cela change, dit franchement : deux rôles peuvent nommer et ordonner
 * la même entrée différemment. Un utilisateur qui les cumule reçoit donc la
 * présentation de l'un d'eux — celui dont le code vient en premier — et la
 * visibilité de tous, par union. Le départage est arbitraire ; l'ancien modèle
 * l'évitait en refusant le réglage. C'est le prix de la liberté demandée, et il
 * ne se paie que sur les comptes multi-rôles.
 *
 * **Les réglages existants ne sont pas perdus** : chaque rôle de l'organisation
 * reçoit une copie de ce qu'elle avait choisi. Un menu déjà composé reste donc
 * en place, à l'identique, pour tout le monde — puis chaque rôle diverge à
 * mesure qu'on le règle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('role_menu_items', function (Blueprint $table) {
            $table->string('label', 60)->nullable()->after('code');
            $table->string('icon', 64)->nullable()->after('label');
            $table->boolean('parent_overridden')->default(false)->after('icon');
            $table->string('parent_code', 64)->nullable()->after('parent_overridden');
            $table->unsignedSmallInteger('position')->nullable()->after('is_visible');
        });

        Schema::create('role_menu_groups', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('role_id', 26);
            $table->string('code', 64);
            $table->string('label', 60);
            $table->string('icon', 64);
            $table->boolean('is_visible')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['role_id', 'code']);
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
        });

        $this->copySettingsToRoles();

        Schema::dropIfExists('organization_menu_items');
        Schema::dropIfExists('organization_menu_groups');
    }

    /**
     * Ce que l'organisation avait choisi devient le point de départ de chacun
     * de ses rôles.
     *
     * Les lignes de rôle existantes ne portaient que la visibilité : elles sont
     * complétées, pas remplacées. Un rôle qui avait déjà masqué une entrée la
     * garde masquée, et hérite du reste.
     */
    private function copySettingsToRoles(): void
    {
        foreach (DB::table('roles')->whereNotNull('organization_id')->get() as $role) {
            $this->copyItems($role);
            $this->copyGroups($role);
        }
    }

    private function copyItems(object $role): void
    {
        $items = DB::table('organization_menu_items')
            ->where('organization_id', $role->organization_id)
            ->get();

        foreach ($items as $item) {
            $existing = DB::table('role_menu_items')
                ->where('role_id', $role->id)
                ->where('code', $item->code)
                ->first();

            $values = [
                'label' => $item->label,
                'icon' => $item->icon,
                'parent_overridden' => $item->parent_overridden,
                'parent_code' => $item->parent_code,
                'position' => $item->position,
                'updated_at' => now(),
            ];

            if ($existing !== null) {
                DB::table('role_menu_items')->where('id', $existing->id)->update($values);

                continue;
            }

            DB::table('role_menu_items')->insert([
                'id' => (string) Str::ulid(),
                'role_id' => $role->id,
                'code' => $item->code,
                'is_visible' => $item->is_visible,
                'created_at' => now(),
                ...$values,
            ]);
        }
    }

    private function copyGroups(object $role): void
    {
        $groups = DB::table('organization_menu_groups')
            ->where('organization_id', $role->organization_id)
            ->get();

        foreach ($groups as $group) {
            DB::table('role_menu_groups')->insert([
                'id' => (string) Str::ulid(),
                'role_id' => $role->id,
                'code' => $group->code,
                'label' => $group->label,
                'icon' => $group->icon,
                'is_visible' => $group->is_visible,
                'position' => $group->position,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Le retour en arrière rend les tables, pas leur contenu.
     *
     * Répartir sur une organisation ce que plusieurs rôles ont pu régler
     * différemment demanderait de choisir lequel a raison — et ce choix n'a pas
     * de bonne réponse. Mieux vaut des tables vides qu'un menu recomposé au
     * hasard.
     */
    public function down(): void
    {
        Schema::create('organization_menu_items', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->string('code', 64);
            $table->string('label', 60)->nullable();
            $table->string('icon', 64)->nullable();
            $table->boolean('parent_overridden')->default(false);
            $table->string('parent_code', 64)->nullable();
            $table->boolean('is_visible')->default(true);
            $table->unsignedSmallInteger('position')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });

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

        Schema::dropIfExists('role_menu_groups');

        Schema::table('role_menu_items', function (Blueprint $table) {
            $table->dropColumn(['label', 'icon', 'parent_overridden', 'parent_code', 'position']);
        });
    }
};
