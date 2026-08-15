<?php

declare(strict_types=1);

namespace App\Modules\ProofOfDelivery\Models;

use App\Modules\Documents\Models\Document;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;
use App\Modules\Tours\Models\TourStop;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Preuve de livraison — donnée historique à valeur probante.
 *
 * Signature et photo sont deux références distinctes vers `documents`. Aucun
 * chemin de fichier n'est stocké ici : le module Documents de la Phase 1 porte
 * déjà le stockage, le type MIME et la taille.
 *
 * Le diagramme ne déclare pas d'`organizationId` : le périmètre passe par la
 * commande. Le scope `inOrganization` est le seul point qui applique cette
 * règle, pour qu'aucune lecture ne puisse l'oublier.
 *
 * Aucun champ `status` : le diagramme n'en définit pas, et le §12 interdit d'en
 * inventer un.
 */
#[Fillable([
    'order_id',
    'order_service_id',
    'tour_stop_id',
    'recipient_name',
    'signature_document_id',
    'photo_document_id',
    'remark',
    'delivered_at',
    'created_by',
])]
class ProofOfDelivery extends Model
{
    use HasFactory;
    use HasUlid;

    public $timestamps = false;

    protected $table = 'proofs_of_delivery';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'delivered_at' => 'datetime',
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
     * @return BelongsTo<OrderService, $this>
     */
    public function orderService(): BelongsTo
    {
        return $this->belongsTo(OrderService::class, 'order_service_id');
    }

    /**
     * @return BelongsTo<TourStop, $this>
     */
    public function tourStop(): BelongsTo
    {
        return $this->belongsTo(TourStop::class, 'tour_stop_id');
    }

    /**
     * @return BelongsTo<Document, $this>
     */
    public function signatureDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'signature_document_id');
    }

    /**
     * @return BelongsTo<Document, $this>
     */
    public function photoDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'photo_document_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Restreint aux preuves dont la commande appartient à l'organisation.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeInOrganization(Builder $query, string $organizationId): void
    {
        $query->whereHas('order', fn (Builder $order) => $order->where('organization_id', $organizationId));
    }
}
