<?php

declare(strict_types=1);

namespace App\Modules\Addresses\Models;

use App\Modules\Contacts\Models\AddressContact;
use App\Modules\Contacts\Models\Contact;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code',
    'name',
    'address_line_1',
    'address_line_2',
    'address_line_3',
    'floor',
    'address_number',
    'route',
    'sublocality',
    'postal_code',
    'city',
    'town',
    'country',
    'latitude',
    'longitude',
    'instructions',
    'time_window_from',
    'time_window_to',
    'is_default',
    'status',
])]
class Address extends Model
{
    use HasFactory;
    use HasUlid;

    public $timestamps = true;

    protected $table = 'addresses';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'is_default' => 'boolean',
        ];
    }

    /**
     * @return HasMany<EntityAddress, $this>
     */
    public function entityAddresses(): HasMany
    {
        return $this->hasMany(EntityAddress::class, 'address_id');
    }

    /**
     * @return BelongsToMany<Contact, $this>
     */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'address_contacts', 'address_id', 'contact_id')
            ->withPivot(['id', 'contact_role', 'is_primary']);
    }

    /**
     * @return HasMany<AddressContact, $this>
     */
    public function addressContacts(): HasMany
    {
        return $this->hasMany(AddressContact::class, 'address_id');
    }
}
