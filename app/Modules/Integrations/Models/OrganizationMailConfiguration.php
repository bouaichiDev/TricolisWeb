<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Models;

use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * La boîte d'envoi d'une organisation.
 *
 * Le mot de passe est **chiffré à l'écriture** et ne ressort jamais par l'API :
 * `password()` existe pour l'expéditeur côté serveur, la ressource n'expose
 * qu'un booléen. Un mot de passe SMTP qui traverse une réponse JSON finit dans
 * un journal, puis dans une capture d'écran.
 */
#[Fillable([
    'organization_id',
    'host',
    'port',
    'encryption',
    'username',
    'from_address',
    'from_name',
    'reply_to',
    'is_active',
])]
class OrganizationMailConfiguration extends Model
{
    use HasUlid;

    protected $table = 'organization_mail_configurations';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * `encrypted_password` n'est **pas** `fillable` : il ne peut être posé que
     * par `setPassword()`, qui chiffre. Un remplissage de masse depuis une
     * requête écrirait le mot de passe en clair.
     *
     * @var list<string>
     */
    protected $hidden = ['encrypted_password'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    /** Chiffre le mot de passe. `null` l'efface. */
    public function setPassword(?string $password): void
    {
        $this->encrypted_password = $password === null || $password === ''
            ? null
            : Crypt::encryptString($password);
    }

    /**
     * Le mot de passe en clair, pour la construction du transport uniquement.
     *
     * Ne jamais renvoyer cette valeur dans une ressource : c'est précisément ce
     * que `$hidden` et l'absence de champ dans la ressource empêchent.
     */
    public function password(): ?string
    {
        return $this->encrypted_password === null
            ? null
            : Crypt::decryptString($this->encrypted_password);
    }

    public function hasPassword(): bool
    {
        return $this->encrypted_password !== null;
    }

    /** @param  Builder<self>  $query */
    public function scopeInOrganization(Builder $query, string $organizationId): void
    {
        $query->where('organization_id', $organizationId);
    }
}
