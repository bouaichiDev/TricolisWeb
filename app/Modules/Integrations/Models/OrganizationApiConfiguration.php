<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Models;

use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Une API externe appelée par l'organisme.
 *
 * Le secret est **chiffré à l'écriture** et ne ressort jamais par l'API :
 * `credentials()` existe pour l'appelant serveur, la ressource n'expose qu'un
 * booléen. Un secret qui traverse une réponse JSON finit dans un journal.
 */
#[Fillable([
    'organization_id',
    'code',
    'name',
    'base_url',
    'auth_type',
    'headers',
    'timeout_seconds',
    'is_active',
])]
class OrganizationApiConfiguration extends Model
{
    use HasUlid;

    protected $table = 'organization_api_configurations';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * `encrypted_credentials` n'est **pas** `fillable` : il ne peut être posé
     * que par `setCredentials()`, qui chiffre. Un remplissage de masse depuis
     * une requête écrirait le secret en clair.
     *
     * @var list<string>
     */
    protected $hidden = ['encrypted_credentials'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'timeout_seconds' => 'integer',
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    /** Chiffre le secret. `null` l'efface. */
    public function setCredentials(?string $secret): void
    {
        $this->encrypted_credentials = $secret === null || $secret === ''
            ? null
            : Crypt::encryptString($secret);
    }

    /**
     * Le secret en clair, pour l'appelant serveur uniquement.
     *
     * Ne jamais renvoyer cette valeur dans une ressource : c'est précisément ce
     * que `$hidden` et l'absence de champ dans la ressource empêchent.
     */
    public function credentials(): ?string
    {
        return $this->encrypted_credentials === null
            ? null
            : Crypt::decryptString($this->encrypted_credentials);
    }

    public function hasCredentials(): bool
    {
        return $this->encrypted_credentials !== null;
    }

    /** @param  Builder<self>  $query */
    public function scopeInOrganization(Builder $query, string $organizationId): void
    {
        $query->where('organization_id', $organizationId);
    }
}
