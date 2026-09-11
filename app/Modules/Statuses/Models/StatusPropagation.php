<?php

declare(strict_types=1);

namespace App\Modules\Statuses\Models;

use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ce qu'un statut entraîne sur les entités voisines.
 *
 * « Tous les colis chargés → le service est chargé » se dit par une paire de
 * statuts : celui qui arrive, celui qu'il entraîne. Chacun porte son entité, et
 * le sens se déduit de la hiérarchie — d'où l'absence de colonne « direction ».
 */
#[Fillable([
    'from_status_id',
    'to_status_id',
    'mode',
    'active',
])]
class StatusPropagation extends Model
{
    use HasFactory;
    use HasUlid;

    /** Tous les enfants doivent porter le statut. */
    public const string MODE_ALL = 'all';

    /** Un seul enfant suffit. */
    public const string MODE_ANY = 'any';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    /** @return BelongsTo<Status, $this> */
    public function fromStatus(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'from_status_id');
    }

    /** @return BelongsTo<Status, $this> */
    public function toStatus(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'to_status_id');
    }

    /** @param  Builder<self>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }
}
