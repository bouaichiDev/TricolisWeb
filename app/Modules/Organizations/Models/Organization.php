<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Models;

use App\Modules\Agencies\Models\Agency;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Customers\Models\Customer;
use App\Modules\Documents\Models\Document;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\Service;
use App\Shared\Database\Concerns\HasUlid;
use App\Shared\Enums\OrganizationStatus;
use Database\Factories\Modules\Organizations\Models\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'code',
    'name',
    'legal_name',
    'registration_number',
    'tax_number',
    'email',
    'phone',
    'preferred_language',
    'timezone',
    'currency_code',
    'status',
    'settings',
    'logo_path',
    'logo_mime_type',
])]
class Organization extends Model
{
    use HasFactory;
    use HasUlid;

    public $timestamps = true;

    protected $table = 'organizations';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrganizationStatus::class,
            'settings' => 'array',
        ];
    }

    /**
     * @return HasMany<OrganizationUser, $this>
     */
    public function organizationUsers(): HasMany
    {
        return $this->hasMany(OrganizationUser::class, 'organization_id');
    }

    /**
     * @return HasOne<Subscription, $this>
     */
    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class, 'organization_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_users', 'organization_id', 'user_id');
    }

    /**
     * @return HasMany<Agency, $this>
     */
    public function agencies(): HasMany
    {
        return $this->hasMany(Agency::class, 'organization_id');
    }

    /**
     * @return HasMany<Role, $this>
     */
    public function roles(): HasMany
    {
        return $this->hasMany(Role::class, 'organization_id');
    }

    /**
     * @return HasMany<Customer, $this>
     */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'organization_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'organization_id');
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'organization_id');
    }

    /**
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'organization_id');
    }

    /**
     * @return HasMany<AuditLog, $this>
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'organization_id');
    }

    protected static function newFactory(): OrganizationFactory
    {
        return OrganizationFactory::new();
    }
}
