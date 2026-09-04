<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Groupe de menu créé sur un rôle.
 *
 * À la différence de `RoleMenuItem`, qui ne fait que **régler** une entrée du
 * catalogue, celui-ci en **est** une : il n'a pas de contrepartie en code.
 * C'est possible parce qu'un groupe n'ouvre rien — ni route, ni permission — et
 * ne peut donc pas mener à un écran qui n'existe pas.
 *
 * Son `code` est opaque et ne change jamais : les entrées rangées dedans le
 * désignent par lui, et le dériver du nom leur ferait perdre leur groupe au
 * premier renommage.
 */
#[Fillable([
    'role_id',
    'code',
    'label',
    'icon',
    'is_visible',
    'position',
])]
class RoleMenuGroup extends Model
{
    use HasUlid;

    /**
     * Préfixe qui sépare les deux espaces de noms.
     *
     * Les codes créés ici et ceux du catalogue se croisent partout — dans
     * `parent_code`, dans `role_menu_items.code`. Sans séparation, un code
     * inventé pourrait un jour coïncider avec un code livré, et l'on réglerait
     * une entrée en croyant en régler une autre.
     */
    public const PREFIX = 'grp-';

    protected $table = 'role_menu_groups';

    protected $keyType = 'string';

    public $incrementing = false;

    public static function newCode(): string
    {
        return self::PREFIX.Str::lower((string) Str::ulid());
    }

    public static function isCustomCode(string $code): bool
    {
        return str_starts_with($code, self::PREFIX);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
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
