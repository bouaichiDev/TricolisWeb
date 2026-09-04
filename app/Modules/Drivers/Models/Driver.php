<?php

declare(strict_types=1);

namespace App\Modules\Drivers\Models;

use App\Modules\Addresses\Models\Address;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Identity\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationUser;
use App\Modules\Providers\Models\Provider;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Chauffeur d'un fournisseur (`Provider 1 — 0..* Driver`).
 *
 * Le diagramme porte `organizationId` sur la classe : l'isolation se lit sur la
 * ligne, sans jointure. Les Actions garantissent que cette organisation reste
 * celle du fournisseur — deux sources pour une même vérité ne divergent que si
 * personne ne les tient.
 *
 * Une seule identité, `name` : le diagramme ne sépare ni prénom ni nom, et ne
 * porte ni téléphone ni courriel — ces informations relèvent de `Contact`.
 */
#[Fillable([
    'organization_id',
    'provider_id',
    'user_id',
    'address_id',
    'contact_id',
    'code',
    'name',
    'status',
])]
class Driver extends Model
{
    use HasFactory;
    use HasUlid;

    public $timestamps = false;

    protected $table = 'drivers';

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
     * @return BelongsTo<Provider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'provider_id');
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
     * Restreint aux chauffeurs de l'organisation.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeInOrganization(Builder $query, string $organizationId): void
    {
        $query->where('organization_id', $organizationId);
    }

    /**
     * Compte avec lequel le chauffeur ouvre l'application.
     *
     * Nul pour les chauffeurs enregistres avant que le lien n'existe : toute
     * creation passe desormais par `CreateDriverAccount`, qui le remplit.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Appartenance du compte a cette organisation.
     *
     * C'est elle que l'interface ouvre, pas l'utilisateur : la fiche d'un membre
     * s'adresse par `organization-users/{id}`, et un meme compte a une
     * appartenance par organisation. Renvoyer l'identifiant de l'utilisateur
     * menait a une page introuvable.
     *
     * @return HasOne<OrganizationUser, $this>
     */
    public function membership(): HasOne
    {
        return $this->hasOne(OrganizationUser::class, 'user_id', 'user_id');
    }
}
