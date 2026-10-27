<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Models\Order;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lien public de suivi d'une commande.
 *
 * Le jeton brut n'existe qu'une fois, dans la réponse qui le crée : la base
 * n'en garde que l'empreinte. Un lien n'est utilisable que s'il est actif,
 * non révoqué et non expiré — `isUsable()` est le seul endroit qui le dit.
 */
#[Fillable([
    'order_id',
    'token_hash',
    'is_active',
    'expires_at',
    'created_by',
    'revoked_at',
    'last_access_at',
    'access_count',
])]
#[Hidden(['token_hash'])]
class PublicTrackingLink extends Model
{
    use HasUlid;

    /** Longueur du jeton brut : 64 caractères alphanumériques, ~380 bits. */
    public const int TOKEN_LENGTH = 64;

    protected $table = 'public_tracking_links';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_access_at' => 'datetime',
            'access_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isUsable(): bool
    {
        return $this->is_active && $this->revoked_at === null && ! $this->isExpired();
    }

    /** `active`, `revoked` ou `expired` — ce que l'écran du client affiche. */
    public function state(): string
    {
        return match (true) {
            ! $this->is_active || $this->revoked_at !== null => 'revoked',
            $this->isExpired() => 'expired',
            default => 'active',
        };
    }
}
