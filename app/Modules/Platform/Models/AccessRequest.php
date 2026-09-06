<?php

declare(strict_types=1);

namespace App\Modules\Platform\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Shared\Database\Concerns\HasUlid;
use App\Shared\Enums\AccessRequestStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une demande d'accès à la plateforme, déposée depuis l'écran de connexion.
 *
 * Elle vit dans le module `Platform` et non dans `Organizations` : tant qu'elle
 * n'est pas acceptée, aucune organisation n'existe — c'est la plateforme, et
 * elle seule, qui la fera naître.
 *
 * Les trois colonnes de décision — `decided_by`, `decided_at`, `decision_note`
 * — sont remplies ensemble. Un refus sans motif se relit six mois plus tard
 * sans qu'on sache pourquoi, et l'administrateur qui a refusé ne s'en souvient
 * pas davantage.
 */
#[Fillable([
    'company_name',
    'contact_name',
    'email',
    'phone',
    'message',
    'status',
    'decision_note',
    'decided_at',
    'decided_by',
    'organization_id',
    'user_id',
])]
class AccessRequest extends Model
{
    use HasUlid;

    protected $table = 'access_requests';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AccessRequestStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    /** @return BelongsTo<User, $this> */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
