<?php

declare(strict_types=1);

namespace App\Modules\Orders\Models;

use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Guarded([])] class OrderLine extends Model
{
    use HasUlid;

    public $timestamps = false;

    protected $keyType = 'string';

    public $incrementing = false;

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'weight' => 'decimal:3', 'volume' => 'decimal:4', 'selling_price' => 'decimal:2'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_line_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_line_id');
    }
}
