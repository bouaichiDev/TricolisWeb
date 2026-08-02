<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Models;

use App\Modules\Addresses\Models\Address;
use App\Shared\Database\Concerns\HasUlid;
use App\Shared\Enums\ContactRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'address_id',
    'contact_id',
    'contact_role',
    'is_primary',
])]
class AddressContact extends Model
{
    use HasUlid;

    public $timestamps = false;

    protected $table = 'address_contacts';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'contact_role' => ContactRole::class,
            'is_primary' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Address, $this>
     */
    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'address_id');
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }
}
