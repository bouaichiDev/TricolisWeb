<?php

declare(strict_types=1);

namespace App\Modules\Catalogs\Models;

use App\Modules\Orders\Models\OrderLine;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Article d'un catalogue client (`CustomerCatalog 1 — 0..* CustomerCatalogItem`).
 */
#[Fillable([
    'catalog_id',
    'article_code',
    'barcode',
    'name',
    'description',
    'weight',
    'volume',
    'length',
    'width',
    'height',
    'status',
])]
class CustomerCatalogItem extends Model
{
    use HasFactory;
    use HasUlid;

    public $timestamps = true;

    protected $table = 'customer_catalog_items';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weight' => 'decimal:3',
            'volume' => 'decimal:4',
            'length' => 'decimal:3',
            'width' => 'decimal:3',
            'height' => 'decimal:3',
        ];
    }

    /**
     * @return BelongsTo<CustomerCatalog, $this>
     */
    public function catalog(): BelongsTo
    {
        return $this->belongsTo(CustomerCatalog::class, 'catalog_id');
    }

    /**
     * @return HasMany<OrderLine, $this>
     */
    public function orderLines(): HasMany
    {
        return $this->hasMany(OrderLine::class, 'catalog_item_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Données recopiées dans une ligne de commande.
     *
     * La ligne devient ensuite autonome : modifier l'article plus tard ne
     * réécrit pas les commandes passées.
     *
     * @return array<string, mixed>
     */
    public function toOrderLineSnapshot(): array
    {
        return [
            'catalog_item_id' => $this->id,
            'article_code' => $this->article_code,
            'barcode' => $this->barcode,
            'name' => $this->name,
            'description' => $this->description,
            'weight' => $this->weight,
            'volume' => $this->volume,
            'length' => $this->length,
            'width' => $this->width,
            'height' => $this->height,
        ];
    }
}
