<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * La sélection de widgets d'un rôle.
 *
 * `widgets` est une liste de `{key, position}`, et rien d'autre. Elle n'est
 * jamais lue telle quelle : `RoleDashboardWidgets` la confronte au catalogue à
 * **chaque** lecture, et laisse tomber les clés qu'il ne connaît pas. Un widget
 * retiré du code disparaît ainsi des configurations qui le mentionnaient encore,
 * sans migration de données et sans erreur.
 *
 * L'absence de ligne et la ligne vide ne disent pas la même chose :
 *
 * ```
 * aucune ligne        → les widgets defaultEnabled du catalogue
 * {"widgets": []}     → ce rôle a choisi de n'en voir aucun
 * ```
 *
 * C'est toute la raison d'être de cette table plutôt que d'une pivot, et c'est
 * aussi pourquoi « réinitialiser » supprime la ligne au lieu de la vider.
 */
#[Fillable([
    'role_id',
    'widgets',
])]
class RoleDashboardConfiguration extends Model
{
    use HasUlid;

    protected $table = 'role_dashboard_configurations';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'widgets' => 'array',
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
