<?php

declare(strict_types=1);

namespace App\Modules\ProviderPortal\Models;

use App\Modules\Identity\Models\Role;
use App\Modules\ProviderPortal\Enums\ProviderInvitationStatus;
use App\Modules\Providers\Models\Provider;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Invitation au portail fournisseur. Le jeton brut n'existe que dans le
 * courriel envoyé : la base n'en garde que l'empreinte.
 */
#[Fillable([
    'provider_id',
    'provider_user_id',
    'email',
    'role_id',
    'token_hash',
    'status',
    'expires_at',
    'invited_by',
    'accepted_at',
])]
#[Hidden(['token_hash'])]
class ProviderUserInvitation extends Model
{
    use HasUlid;

    protected $table = 'provider_user_invitations';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProviderInvitationStatus::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Provider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'provider_id');
    }

    /**
     * @return BelongsTo<ProviderUser, $this>
     */
    public function providerUser(): BelongsTo
    {
        return $this->belongsTo(ProviderUser::class, 'provider_user_id');
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
