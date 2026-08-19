<?php

declare(strict_types=1);

namespace App\Modules\Statuses\Models;

use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un statut du référentiel commun.
 *
 * Le couple (`source`, `code`) identifie le statut du point de vue du domaine :
 * c'est `code` qui est stocké dans les colonnes `status` des tables métier, et
 * `source` qui dit de quelle entité il s'agit — « draft » n'a pas le même sens
 * pour une commande et pour un colis.
 *
 * Aucune clé étrangère ne relie ces colonnes : elles portent déjà des données,
 * appartiennent à des tables différentes et restent de simples chaînes. Le lien
 * est vérifié à l'application, pas imposé par le schéma.
 */
#[Fillable([
    'source',
    'status',
    'code',
    'label',
    'icon',
    'active',
    'is_to_send',
    'allows_content_changes',
    'requires_reason',
    'position',
])]
class Status extends Model
{
    use HasFactory;
    use HasUlid;

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => 'integer',
            'active' => 'boolean',
            'is_to_send' => 'boolean',
            'allows_content_changes' => 'boolean',
            'requires_reason' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * Transitions qui partent de ce statut.
     *
     * @return HasMany<StatusTransition, $this>
     */
    public function outgoing(): HasMany
    {
        return $this->hasMany(StatusTransition::class, 'from_status_id');
    }

    /** @return HasMany<StatusTransition, $this> */
    public function incoming(): HasMany
    {
        return $this->hasMany(StatusTransition::class, 'to_status_id');
    }

    /** @param  Builder<self>  $query */
    public function scopeForSource(Builder $query, string $source): void
    {
        $query->where('source', $source);
    }

    /** @param  Builder<self>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }
}
