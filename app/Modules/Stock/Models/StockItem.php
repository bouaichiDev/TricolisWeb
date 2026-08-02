<?php

declare(strict_types=1);

namespace App\Modules\Stock\Models;

use App\Modules\Catalogs\Models\CustomerCatalogItem;
use App\Modules\Customers\Models\Customer;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Article de stock d'un client (`Customer "1" -- "0..*" StockItem`).
 *
 * Le diagramme ne pose pas d'`organizationId` : le périmètre passe par le
 * client. Le scope `inOrganization` est le seul point qui applique cette règle.
 *
 * Ni quantité ni emplacement ici — le stock réel vit dans `StockBalance`, un
 * article pouvant être présent à plusieurs emplacements à la fois.
 */
#[Fillable([
    'customer_id',
    'catalog_item_id',
    'article_code',
    'barcode',
    'description',
    'status',
])]
class StockItem extends Model
{
    use HasFactory;
    use HasUlid;

    public $timestamps = false;

    protected $table = 'stock_items';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * @return BelongsTo<CustomerCatalogItem, $this>
     */
    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CustomerCatalogItem::class, 'catalog_item_id');
    }

    /**
     * @return HasMany<StockBalance, $this>
     */
    public function balances(): HasMany
    {
        return $this->hasMany(StockBalance::class, 'stock_item_id');
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'stock_item_id');
    }

    /**
     * @return HasMany<StockReservation, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(StockReservation::class, 'stock_item_id');
    }

    /**
     * L'article est-il encore engagé ?
     *
     * Sert le refus de suppression : un article qui porte du stock, un
     * historique de mouvements ou une réservation ne s'efface pas.
     */
    public function isInUse(): bool
    {
        return $this->balances()->where('quantity', '>', 0)->exists()
            || $this->movements()->exists()
            || $this->reservations()->exists();
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeInOrganization(Builder $query, string $organizationId): void
    {
        $query->whereHas('customer', fn (Builder $c) => $c->where('organization_id', $organizationId));
    }
}
