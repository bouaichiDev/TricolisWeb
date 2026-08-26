<?php

declare(strict_types=1);

namespace App\Modules\Planning\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Statuses\Services\StatusMachine;
use App\Modules\Tours\Models\Tour;
use App\Shared\Database\MorphMap;
use App\Shared\Support\AuditContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Fait changer une tournée d'état.
 *
 * C'est par ici que passent la validation d'un brouillon et son annulation :
 * une seule porte, parce que les deux font la même chose — vérifier que le
 * passage est permis, recalculer ce qui en dépend, écrire le journal.
 *
 * **Le référentiel décide, pas ce code.** `status_transitions` dit quels
 * passages existent ; les enchaîner en dur ici rendrait impossible d'en ajouter
 * un sans livrer une version. Le cycle des tournées y a été semé le 26 août
 * 2026 : brouillon → confirmée → planifiée → en cours → terminée, l'annulation
 * restant ouverte tant que la tournée n'est pas terminée.
 *
 * **Tout se joue dans une transaction, la tournée verrouillée.** Deux
 * validations simultanées produiraient deux fois les mêmes effets, ou une
 * tournée à demi validée si la seconde échouait au milieu.
 */
final readonly class ChangeTourStatus
{
    public function __construct(
        private StatusMachine $machine,
        private WriteAuditLog $audit,
        private TourTotals $totals,
    ) {}

    /**
     * @throws ValidationException quand le passage n'est pas prévu
     */
    public function execute(Tour $tour, string $target, AuditContext $context): Tour
    {
        return DB::transaction(function () use ($tour, $target, $context): Tour {
            /** @var Tour|null $locked */
            $locked = Tour::whereKey($tour->id)->lockForUpdate()->first();

            if ($locked === null) {
                throw new ModelNotFoundException('Tournée introuvable.');
            }

            $from = $locked->status->value;

            // Relu apres le verrou : entre la lecture du controleur et ce
            // point, quelqu'un a pu valider la meme tournee.
            if ($from === $target) {
                return $locked;
            }

            if (! $this->machine->allows(MorphMap::TOUR, $from, $target)) {
                throw ValidationException::withMessages([
                    'status' => sprintf(
                        'Une tournée « %s » ne peut pas passer à « %s ».',
                        $this->label($from),
                        $this->label($target),
                    ),
                ]);
            }

            $before = $locked->only(['status', 'total_packages', 'total_customers', 'distance_meters']);

            $locked->forceFill(['status' => $target])->save();

            // Les totaux sont refaits a la sortie du brouillon : ce qui a ete
            // planifie fait foi, et des compteurs restes a zero donneraient une
            // tournee sans poids ni colis a qui la prend en charge.
            if ($from === 'draft') {
                $this->totals->recalculate($locked);
            }

            $this->audit->execute(
                $context->organizationId,
                $context->user,
                'tour.status_changed',
                $locked,
                $before,
                $locked->fresh()->only(['status', 'total_packages', 'total_customers', 'distance_meters']),
                null,
                $context->ipAddress,
            );

            return $locked->fresh();
        });
    }

    /** Libellé du référentiel, ou le code lorsqu'il n'y en a pas. */
    private function label(string $code): string
    {
        return $this->machine->status(MorphMap::TOUR, $code)?->label ?? $code;
    }
}
