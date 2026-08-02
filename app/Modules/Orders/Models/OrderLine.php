<?php

declare(strict_types=1);

namespace App\Modules\Orders\Models;

use App\Modules\Catalogs\Models\CustomerCatalogItem;
use App\Modules\Packages\Models\Package;
use App\Modules\Packages\Models\PackageOrderLine;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ligne de commande.
 *
 * Une ligne issue du catalogue conserve une copie des données de l'article :
 * `catalog_item_id` ne sert qu'à retrouver la source, jamais à recalculer la
 * ligne à l'affichage.
 */
#[Fillable([
    'order_id',
    'catalog_item_id',
    'parent_line_id',
    'external_reference',
    'article_code',
    'barcode',
    'name',
    'description',
    'quantity',
    'reserved_quantity',
    'prepared_quantity',
    'delivered_quantity',
    'weight',
    'volume',
    'length',
    'width',
    'height',
    'purchase_price',
    'selling_price',
    'status',
])]
class OrderLine extends Model
{
    use HasFactory;
    use HasUlid;

    public $timestamps = false;

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'reserved_quantity' => 'decimal:3',
            'prepared_quantity' => 'decimal:3',
            'delivered_quantity' => 'decimal:3',
            'weight' => 'decimal:3',
            'volume' => 'decimal:4',
            'length' => 'decimal:3',
            'width' => 'decimal:3',
            'height' => 'decimal:3',
            'purchase_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<CustomerCatalogItem, $this>
     */
    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CustomerCatalogItem::class, 'catalog_item_id');
    }

    /**
     * @return BelongsTo<OrderLine, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_line_id');
    }

    /**
     * @return HasMany<OrderLine, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_line_id');
    }

    /**
     * @return HasMany<PackageOrderLine, $this>
     */
    public function packageOrderLines(): HasMany
    {
        return $this->hasMany(PackageOrderLine::class, 'order_line_id');
    }

    /**
     * @return BelongsToMany<Package, $this>
     */
    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(Package::class, 'package_order_lines', 'order_line_id', 'package_id')
            ->withPivot(['id', 'quantity']);
    }

    /**
     * Quantité déjà répartie dans des colis.
     */
    public function assignedQuantity(): float
    {
        return (float) $this->packageOrderLines()->sum('quantity');
    }
}
