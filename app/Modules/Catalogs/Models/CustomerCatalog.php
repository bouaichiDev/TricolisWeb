<?php

declare(strict_types=1);

namespace App\Modules\Catalogs\Models;

use App\Modules\Customers\Models\Customer;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catalogue d'articles d'un client (`Customer 1 — 0..* CustomerCatalog`).
 *
 * Le catalogue décrit ce que le client fait transporter ; il ne représente pas
 * des quantités disponibles, qui relèvent du module Stock.
 */
#[Fillable([
    'customer_id',
    'code',
    'name',
    'description',
    'status',
])]
class CustomerCatalog extends Model
{
    use HasFactory;
    use HasUlid;

    public $timestamps = true;

    protected $table = 'customer_catalogs';

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
     * @return HasMany<CustomerCatalogItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CustomerCatalogItem::class, 'catalog_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
