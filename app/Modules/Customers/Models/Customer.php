<?php

declare(strict_types=1);

namespace App\Modules\Customers\Models;

use App\Modules\Catalogs\Models\CustomerCatalog;
use App\Modules\Customers\Enums\CustomerStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Organizations\Models\Organization;
use App\Shared\Database\Concerns\HasUlid;
use Database\Factories\Modules\Customers\Models\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'organization_id',
    'code',
    'name',
    'legal_name',
    'email',
    'phone',
    'payment_mode',
    'communication_mode',
    'catalog_enabled',
    'stock_enabled',
    'package_enabled',
    'appointment_enabled',
    'tracking_enabled',
    'status',
])]
class Customer extends Model
{
    use HasFactory;
    use HasUlid;

    public $timestamps = true;

    protected $table = 'customers';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'catalog_enabled' => 'boolean',
            'stock_enabled' => 'boolean',
            'package_enabled' => 'boolean',
            'appointment_enabled' => 'boolean',
            'tracking_enabled' => 'boolean',
            'status' => CustomerStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    /**
     * @return HasMany<CustomerSite, $this>
     */
    public function sites(): HasMany
    {
        return $this->hasMany(CustomerSite::class, 'customer_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @return HasMany<CustomerCatalog, $this>
     */
    public function catalogs(): HasMany
    {
        return $this->hasMany(CustomerCatalog::class, 'customer_id');
    }

    protected static function newFactory(): CustomerFactory
    {
        return CustomerFactory::new();
    }
}
