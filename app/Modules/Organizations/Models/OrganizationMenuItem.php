<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Models;

use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Réglage de menu d'une organisation pour une entrée du catalogue.
 *
 * L'absence de ligne vaut « valeurs par défaut » : une organisation qui ne
 * personnalise rien n'a aucune ligne, et une entrée ajoutée au catalogue
 * apparaît partout sans migration de données.
 */
#[Fillable([
    'organization_id',
    'code',
    'is_visible',
    'position',
])]
class OrganizationMenuItem extends Model
{
    use HasUlid;

    protected $table = 'organization_menu_items';

    protected $keyType = 'string';

    public $incrementing = false;

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
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }
}
