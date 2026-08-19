<?php

declare(strict_types=1);

namespace App\Modules\Statuses\Models;

use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une transition autorisée entre deux statuts.
 *
 * `is_manual` distingue ce qu'un opérateur peut poser de ce que seuls les
 * modules produisent : passer en « planifiée » est une transition légitime,
 * mais c'est la planification qui la déclenche, pas un clic.
 */
#[Fillable([
    'from_status_id',
    'to_status_id',
    'is_manual',
])]
class StatusTransition extends Model
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
        return ['is_manual' => 'boolean'];
    }

    /** @return BelongsTo<Status, $this> */
    public function from(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'from_status_id');
    }

    /** @return BelongsTo<Status, $this> */
    public function to(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'to_status_id');
    }
}
