<?php

declare(strict_types=1);

namespace App\Modules\Packages\Models;

use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderLine;
use App\Modules\Tracking\Models\Concerns\TracksStatusChanges;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Colis d'une commande, éventuellement imbriqué dans un colis parent
 * (`Order 1 *-- 0..* Package`, `Package 0..1 --> 0..* Package`).
 */
#[Fillable([
    'order_id',
    'parent_package_id',
    'package_type_id',
    'grouping_type_id',
    'current_stock_location_id',
    'barcode',
    'reference',
    'description',
    'quantity',
    'weight',
    'volume',
    'length',
    'width',
    'height',
    'status',
])]
class Package extends Model
{
    use HasFactory;
    use HasUlid;
    use TracksStatusChanges;

    /**
     * Profondeur maximale de l'imbrication.
     *
     * Le diagramme n'en fixe aucune ; cette limite protège des arbres
     * pathologiques et borne le coût du chargement récursif.
     */
    public const int MAX_DEPTH = 5;

    public $timestamps = true;

    protected $table = 'packages';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'weight' => 'decimal:3',
            'volume' => 'decimal:4',
            'length' => 'decimal:3',
            'width' => 'decimal:3',
            'height' => 'decimal:3',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * @return BelongsTo<Package, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_package_id');
    }

    /**
     * @return HasMany<Package, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_package_id');
    }

    /**
     * @return BelongsTo<PackageType, $this>
     */
    public function packageType(): BelongsTo
    {
        return $this->belongsTo(PackageType::class, 'package_type_id');
    }

    /**
     * @return BelongsTo<GroupingType, $this>
     */
    public function groupingType(): BelongsTo
    {
        return $this->belongsTo(GroupingType::class, 'grouping_type_id');
    }

    /**
     * @return HasMany<PackageOrderLine, $this>
     */
    public function packageOrderLines(): HasMany
    {
        return $this->hasMany(PackageOrderLine::class, 'package_id');
    }

    /**
     * @return BelongsToMany<OrderLine, $this>
     */
    public function orderLines(): BelongsToMany
    {
        return $this->belongsToMany(OrderLine::class, 'package_order_lines', 'package_id', 'order_line_id')
            ->withPivot(['id', 'quantity']);
    }
}
