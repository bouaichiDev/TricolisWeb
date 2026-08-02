<?php

declare(strict_types=1);

namespace App\Modules\Agencies\Models;

use App\Modules\Orders\Models\Order;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'agency_id',
    'code',
    'name',
    'status',
])]
class Depot extends Model
{
    use HasFactory;
    use HasUlid;

    public $timestamps = true;

    protected $table = 'depots';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return BelongsTo<Agency, $this>
     */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'depot_id');
    }
}
