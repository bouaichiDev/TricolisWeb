<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Models;

use App\Modules\Addresses\Models\Address;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'first_name',
    'last_name',
    'phone',
    'mobile',
    'email',
    'preferred_language',
    'is_active',
])]
class Contact extends Model
{
    use HasFactory;
    use HasUlid;

    public $timestamps = true;

    protected $table = 'contacts';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<EntityContact, $this>
     */
    public function entityContacts(): HasMany
    {
        return $this->hasMany(EntityContact::class, 'contact_id');
    }

    /**
     * @return BelongsToMany<Address, $this>
     */
    public function addresses(): BelongsToMany
    {
        return $this->belongsToMany(Address::class, 'address_contacts', 'contact_id', 'address_id')
            ->withPivot(['id', 'contact_role', 'is_primary']);
    }
}
