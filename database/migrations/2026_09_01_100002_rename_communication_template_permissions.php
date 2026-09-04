<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Les permissions suivent la table qu'elles gouvernent.
 *
 * `communication_templates.view` autorisait à voir des modèles de message ; la
 * même ligne autorise maintenant à voir tous les modèles, facture comprise. En
 * créer une seconde famille aurait obligé chaque écran à demander deux
 * permissions pour une seule table.
 *
 * **Les rôles sont préservés.** Seuls le code et le module changent : les
 * lignes de `role_permissions` pointent sur l'identifiant, que rien ne touche.
 * Un administrateur qui pouvait éditer les modèles hier le peut encore.
 */
return new class extends Migration
{
    /** @var array<string, string> */
    private const array RENAMES = [
        'communication_templates.view' => 'templates.view',
        'communication_templates.create' => 'templates.create',
        'communication_templates.update' => 'templates.update',
        'communication_templates.delete' => 'templates.delete',
    ];

    public function up(): void
    {
        foreach (self::RENAMES as $old => $new) {
            DB::table('permissions')->where('code', $old)->update([
                'code' => $new,
                'module' => 'templates',
            ]);
        }

        DB::table('permissions')->where('code', 'templates.view')
            ->update(['name' => 'Voir les modèles']);
        DB::table('permissions')->where('code', 'templates.create')
            ->update(['name' => 'Créer un modèle']);
        DB::table('permissions')->where('code', 'templates.update')
            ->update(['name' => 'Modifier un modèle']);
        DB::table('permissions')->where('code', 'templates.delete')
            ->update(['name' => 'Supprimer un modèle']);
    }

    public function down(): void
    {
        foreach (self::RENAMES as $old => $new) {
            DB::table('permissions')->where('code', $new)->update([
                'code' => $old,
                'module' => 'communication_templates',
            ]);
        }
    }
};
