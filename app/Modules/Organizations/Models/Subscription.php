<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Models;

use App\Modules\Organizations\Enums\SubscriptionStatus;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Abonnement d'une organisation (`Organization 1 — 0..1 Subscription`).
 */
#[Fillable([
    'organization_id',
    'plan_code',
    'status',
    'starts_at',
    'ends_at',
    'trial_ends_at',
])]
class Subscription extends Model
{
    use HasFactory;
    use HasUlid;

    public $timestamps = false;

    protected $table = 'subscriptions';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'trial_ends_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    /**
     * L'abonnement est-il en période d'essai à la date du jour ?
     */
    public function onTrial(): bool
    {
        return $this->trial_ends_at !== null && $this->trial_ends_at->isFuture();
    }

    /**
     * L'échéance de l'abonnement est-elle dépassée ?
     */
    public function hasEnded(): bool
    {
        return $this->ends_at !== null && $this->ends_at->isPast();
    }

    /**
     * L'abonnement donne-t-il effectivement accès à la plateforme ?
     */
    public function grantsAccess(): bool
    {
        return $this->status->grantsAccess() && ! $this->hasEnded();
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeGrantingAccess(Builder $query): void
    {
        $query
            ->whereIn('status', [SubscriptionStatus::TRIALING, SubscriptionStatus::ACTIVE])
            ->where(fn (Builder $builder) => $builder->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }
}
