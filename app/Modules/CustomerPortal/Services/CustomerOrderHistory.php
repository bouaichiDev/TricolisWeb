<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Services;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\CustomerPortal\Models\CustomerUser;
use App\Modules\Orders\Models\Order;
use App\Shared\Database\MorphMap;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * L'historique **fonctionnel** d'une commande, tel qu'un client peut le lire.
 *
 * Il se lit dans l'audit existant, mais n'en rend qu'une projection :
 *
 * - quatre événements seulement — créée, modifiée, statut changé, dupliquée ;
 * - ni valeurs brutes (elles contiennent la remarque interne), ni adresse IP ;
 * - l'auteur est nommé s'il est membre du portail de ce client, sinon c'est
 *   « Votre transporteur » : les collaborateurs du transporteur ne sont pas
 *   exposés par leur nom.
 */
final readonly class CustomerOrderHistory
{
    private const array EVENTS = [
        'created' => 'Commande créée',
        'updated' => 'Commande modifiée',
        'status_changed' => 'Statut modifié',
        'duplicated' => 'Commande créée par duplication',
    ];

    public function __construct(private CustomerStatusLabels $labels) {}

    public function paginate(Order $order, int $perPage = 20): LengthAwarePaginator
    {
        $members = CustomerUser::where('customer_id', $order->customer_id)->pluck('user_id')->flip();

        return AuditLog::query()
            ->with('user:id,first_name,last_name')
            ->where('organization_id', $order->organization_id)
            ->where('entity_type', MorphMap::ORDER)
            ->where('entity_id', $order->id)
            ->whereIn('action', array_keys(self::EVENTS))
            ->latest('created_at')
            ->paginate($perPage)
            ->through(fn (AuditLog $log): array => [
                'id' => $log->id,
                'type' => $log->action,
                'label' => self::EVENTS[$log->action],
                'at' => $log->created_at?->toIso8601String(),
                'actor' => $log->user !== null && $members->has($log->user_id)
                    ? trim($log->user->first_name.' '.$log->user->last_name)
                    : 'Votre transporteur',
                'status' => $log->action === 'status_changed' ? [
                    'from' => $this->labels->present(MorphMap::ORDER, $log->old_values['status'] ?? null)['label'],
                    'to' => $this->labels->present(MorphMap::ORDER, $log->new_values['status'] ?? null)['label'],
                ] : null,
            ]);
    }
}
