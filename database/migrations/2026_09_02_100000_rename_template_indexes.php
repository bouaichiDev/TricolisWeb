<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Les index portent le nom de leur table.
 *
 * `RENAME TABLE` déplace les index avec la table mais garde leurs noms : neuf
 * index de `templates` s'appelaient encore `communication_templates_*`. Rien
 * n'en souffrait — un index sert par ses colonnes, pas par son nom — mais qui
 * inspecte le schéma y lit une table qui n'existe plus, et se demande s'il en
 * reste deux.
 *
 * Renommer un index ne le reconstruit pas : MySQL réécrit une entrée de
 * dictionnaire, sans toucher aux données ni verrouiller la table longtemps.
 *
 * L'opération est conditionnée à l'existence de l'ancien nom : sur une base
 * créée après le renommage, Laravel a déjà nommé les index correctement, et
 * rejouer aveuglément échouerait.
 */
return new class extends Migration
{
    /** @var array<string, string> */
    private const array RENAMES = [
        'communication_templates_organization_id_code_unique' => 'templates_organization_id_code_unique',
        'communication_templates_organization_id_index' => 'templates_organization_id_index',
        'communication_templates_service_id_index' => 'templates_service_id_index',
        'communication_templates_channel_index' => 'templates_channel_index',
        'communication_templates_template_type_index' => 'templates_template_type_index',
        'communication_templates_language_index' => 'templates_language_index',
        'communication_templates_is_default_index' => 'templates_is_default_index',
        'communication_templates_is_active_index' => 'templates_is_active_index',
        'communication_templates_created_at_index' => 'templates_created_at_index',
    ];

    public function up(): void
    {
        foreach (self::RENAMES as $from => $to) {
            $this->rename($from, $to);
        }
    }

    public function down(): void
    {
        foreach (self::RENAMES as $from => $to) {
            $this->rename($to, $from);
        }
    }

    private function rename(string $from, string $to): void
    {
        if (! Schema::hasTable('templates') || ! $this->exists($from)) {
            return;
        }

        DB::statement("ALTER TABLE `templates` RENAME INDEX `{$from}` TO `{$to}`");
    }

    private function exists(string $index): bool
    {
        return DB::select('SHOW INDEX FROM `templates` WHERE Key_name = ?', [$index]) !== [];
    }
};
