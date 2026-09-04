<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Réglage de menu d'un rôle pour une entrée du catalogue.
 *
 * L'absence de ligne vaut « valeurs par défaut » : un rôle qui ne personnalise
 * rien n'a aucune ligne, et une entrée ajoutée au catalogue apparaît partout
 * sans migration de données.
 *
 * `label` et `icon` obéissent à la même règle : null signifie « ce que dit le
 * catalogue », pas « pas de libellé ». Un rôle qui n'a rien renommé suit donc
 * les traductions livrées, y compris les futures.
 *
 * `parent_code` fait exception, et c'est pourquoi `parent_overridden`
 * l'accompagne : « au premier niveau » s'y écrit `null`, ce qui rend le choix
 * indistinguable de son absence. Le drapeau porte la décision, la colonne sa
 * cible.
 */
#[Fillable([
    'role_id',
    'code',
    'label',
    'icon',
    'parent_overridden',
    'parent_code',
    'is_visible',
    'position',
])]
class RoleMenuItem extends Model
{
    use HasUlid;

    protected $table = 'role_menu_items';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
            'parent_overridden' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }
}
