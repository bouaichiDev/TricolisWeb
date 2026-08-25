<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Déplace les trois référentiels de type dans `type_items`.
 *
 * **Les identifiants sont conservés.** `packages.package_type_id` et
 * `vehicles.vehicle_type_id` continuent de désigner la même valeur : seule la
 * table qui l'héberge change, et les clés étrangères sont repointées. Réattribuer
 * des identifiants aurait obligé à réécrire chaque ligne qui s'y réfère.
 */
return new class extends Migration
{
    /** Ancienne table => code et libellé de la source correspondante. */
    private const array SOURCES = [
        'package_types' => ['package', 'Type de colis'],
        'grouping_types' => ['grouping', 'Type de groupage'],
        'vehicle_types' => ['vehicle', 'Type de véhicule'],
    ];

    public function up(): void
    {
        // Les trois sources structurelles existent pour **chaque** organisation,
        // meme celle qui n'avait encore aucun type : sans elles, l'ecran des
        // referentiels serait vide et rien ne pourrait y etre ajoute.
        $organizations = DB::table('organizations')->orderBy('id')->pluck('id');

        foreach (self::SOURCES as $table => [$code, $name]) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($organizations as $organizationId) {
                $typeId = $this->ensureType((string) $organizationId, $code, $name);

                DB::table($table)->where('organization_id', $organizationId)->orderBy('id')
                    ->each(function (object $row) use ($typeId, $organizationId): void {
                        DB::table('type_items')->insert([
                            'id' => $row->id,
                            'organization_id' => $organizationId,
                            'type_id' => $typeId,
                            'code' => $row->code,
                            'name' => $row->name,
                            'status' => $row->status ?? 'active',
                            'position' => 0,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    });
            }
        }

        $this->repoint();

        foreach (array_keys(self::SOURCES) as $table) {
            Schema::dropIfExists($table);
        }
    }

    public function down(): void
    {
        foreach (self::SOURCES as $table => [$code]) {
            Schema::create($table, function (Blueprint $blueprint): void {
                $blueprint->char('id', 26)->primary();
                $blueprint->char('organization_id', 26);
                $blueprint->string('code', 64);
                $blueprint->string('name');
                $blueprint->string('status', 32)->default('active');
                $blueprint->timestamps();

                $blueprint->unique(['organization_id', 'code']);
                $blueprint->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            });

            // `select` explicite : la jointure expose deux colonnes `id`, `code`
            // et `status`, et sans lui ce sont celles de `types` qui seraient
            // recopiées — donc de mauvaises lignes, sous de mauvais codes.
            DB::table('type_items')
                ->join('types', 'types.id', '=', 'type_items.type_id')
                ->where('types.code', $code)
                ->select('type_items.*')
                ->orderBy('type_items.id')
                ->each(function (object $row) use ($table): void {
                    DB::table($table)->insert([
                        'id' => $row->id,
                        'organization_id' => $row->organization_id,
                        'code' => $row->code,
                        'name' => $row->name,
                        'status' => $row->status,
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                    ]);
                });
        }

        $this->repoint(back: true);

        DB::table('type_items')->delete();
        DB::table('types')->where('is_system', true)->delete();
    }

    /**
     * Repointe les clés étrangères vers `type_items`, ou vers les anciennes
     * tables lorsqu'on revient en arrière.
     */
    private function repoint(bool $back = false): void
    {
        $targets = [
            ['packages', 'package_type_id', $back ? 'package_types' : 'type_items'],
            ['packages', 'grouping_type_id', $back ? 'grouping_types' : 'type_items'],
            ['vehicles', 'vehicle_type_id', $back ? 'vehicle_types' : 'type_items'],
        ];

        foreach ($targets as [$table, $column, $references]) {
            Schema::table($table, function (Blueprint $blueprint) use ($column, $references): void {
                $blueprint->dropForeign([$column]);
                $blueprint->foreign($column)->references('id')->on($references)->restrictOnDelete();
            });
        }
    }

    /** Crée la source si elle manque, et rend son identifiant. */
    private function ensureType(string $organizationId, string $code, string $name): string
    {
        $existing = DB::table('types')
            ->where('organization_id', $organizationId)->where('code', $code)->value('id');

        if ($existing !== null) {
            return (string) $existing;
        }

        $id = (string) Str::ulid();

        DB::table('types')->insert([
            'id' => $id,
            'organization_id' => $organizationId,
            'code' => $code,
            'name' => $name,
            'status' => 'active',
            'is_system' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }
};
