<?php

declare(strict_types=1);

namespace App\Modules\Planning\Services;

use App\Modules\Orders\Enums\OrderServiceStatus;
use App\Modules\Orders\Models\OrderService;
use App\Modules\Tours\Models\TourStopService;

/**
 * Ce qui peut entrer dans une tournée, et pourquoi le reste ne peut pas.
 *
 * **Deux conditions, et une seule fait autorité pour chacune.** Le statut dit
 * si le service attend d'être planifié ; l'affectation active dit s'il l'est
 * déjà ailleurs. Un service peut avoir été planifié dix fois dans son
 * histoire — c'est la replanification — mais une seule de ces affectations est
 * active à la fois.
 *
 * Un service en échec n'est **pas** directement planifiable : il doit d'abord
 * repasser en attente, transition que le référentiel autorise ou non. Le
 * remettre d'office dans une tournée reviendrait à décider à la place de qui
 * doit reprendre contact avec le client.
 */
final readonly class PlanningEligibility
{
    /**
     * Statuts depuis lesquels un service peut être planifié.
     *
     * `draft` en est exclu : la commande n'est pas arrêtée. `planned` aussi —
     * il l'est déjà, et le replanifier suppose de retirer l'affectation en
     * cours, ce qui est un autre geste.
     */
    public const array PLANNABLE_STATUSES = [
        OrderServiceStatus::PENDING->value,
        OrderServiceStatus::READY_TO_PLAN->value,
    ];

    /** Motif rendu quand le statut ne permet pas la planification. */
    public const string REASON_STATUS = 'status';

    /** Motif rendu quand le service est déjà affecté à une tournée. */
    public const string REASON_ALREADY_ASSIGNED = 'already_assigned';

    /** Motif rendu quand le service n'a pas d'adresse exploitable. */
    public const string REASON_NO_ADDRESS = 'no_address';

    /**
     * Rend `null` quand le service est planifiable, sinon le motif du refus.
     *
     * Le motif est un code, pas une phrase : c'est l'interface qui le traduit,
     * et le serveur n'a pas à parler la langue de qui regarde.
     */
    public function refusalFor(OrderService $service): ?string
    {
        // L'affectation d'abord : un service planifie ailleurs porte aussi le
        // statut « planifiee », et repondre « mauvais statut » enverrait
        // chercher du cote de la commande au lieu de la tournee qui le tient.
        if ($this->hasActiveAssignment($service)) {
            return self::REASON_ALREADY_ASSIGNED;
        }

        if (! in_array($service->status?->value ?? (string) $service->status, self::PLANNABLE_STATUSES, true)) {
            return self::REASON_STATUS;
        }

        if ($service->address_id === null) {
            return self::REASON_NO_ADDRESS;
        }

        return null;
    }

    /**
     * Ce service est-il déjà porté par une tournée ?
     *
     * Les affectations historiques ne comptent pas : elles racontent où le
     * service est passé, pas où il va.
     */
    public function hasActiveAssignment(OrderService $service): bool
    {
        return TourStopService::where('order_service_id', $service->id)
            ->where('is_active_assignment', true)
            ->exists();
    }
}
