<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Models;

use App\Modules\CustomerPortal\Enums\CustomerInvitationStatus;
use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Invitation au portail. Le jeton brut n'existe que dans le courriel envoyé :
 * la base n'en garde que l'empreinte.
 */
#[Fillable([
    'customer_id',
    'customer_user_id',
    'email',
    'role_id',
    'token_hash',
    'status',
    'expires_at',
    'invited_by',
    'accepted_at',
])]
#[Hidden(['token_hash'])]
class CustomerUserInvitation extends Model
{
    use HasUlid;

    protected $table = 'customer_user_invitations';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CustomerInvitationStatus::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * @return BelongsTo<CustomerUser, $this>
     */
    public function customerUser(): BelongsTo
    {
        return $this->belongsTo(CustomerUser::class, 'customer_user_id');
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
