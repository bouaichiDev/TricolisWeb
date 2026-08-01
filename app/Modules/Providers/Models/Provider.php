<?php

declare(strict_types=1);

namespace App\Modules\Providers\Models;

use App\Modules\Addresses\Models\Address;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Drivers\Models\Driver;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Organizations\Models\Organization;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Fournisseur de transport (`Organization 1 — 0..* Provider`).
 *
 * L'adresse et le contact sont portés en clé étrangère directe, comme le pose
 * le diagramme : `Provider "0..*" --> "0..1" Address`. Les deux sont donc
 * facultatifs, et ne passent pas par `EntityAddress` / `EntityContact`.
 */
#[Fillable([
    'organization_id',
    'address_id',
    'contact_id',
    'code',
    'name',
    'status',
])]
class Provider extends Model
{
    use HasFactory;
    use HasUlid;

    public $timestamps = false;

    protected $table = 'providers';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
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

    /**
     * @return HasMany<Driver, $this>
     */
    public function drivers(): HasMany
    {
        return $this->hasMany(Driver::class, 'provider_id');
    }

    /**
     * @return HasMany<Vehicle, $this>
     */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class, 'provider_id');
    }

    /**
     * Le fournisseur est-il encore rattaché à des ressources ?
     *
     * Sert le refus de suppression : un fournisseur qui a des chauffeurs ou des
     * véhicules ne se supprime pas sans les traiter d'abord.
     */
    public function hasResources(): bool
    {
        return $this->drivers()->exists() || $this->vehicles()->exists();
    }
}
