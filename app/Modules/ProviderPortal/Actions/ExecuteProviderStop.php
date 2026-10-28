<?php

declare(strict_types=1);

namespace App\Modules\ProviderPortal\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\ProviderPortal\Services\ProviderTourScope;
use App\Modules\Tours\Actions\UpdateTourStopAction;
use App\Modules\Tours\DTOs\UpdateTourStopData;
use App\Modules\Tours\Enums\TourStatus;
use App\Modules\Tours\Enums\TourStopStatus;
use App\Modules\Tours\Models\TourStop;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Faire avancer un arrêt d'une tournée en cours (cycle encadré) :
 *
 * ```
 * pending ─Arrivé─► arrived ─Terminé─► completed
 * pending | arrived ─Passé (motif)─► skipped
 * ```
 *
 * Un arrêt mis « en cours » par l'interne se termine ou se passe comme un
 * arrêt atteint. Rien ne bouge pendant qu'un planificateur réserve la tournée.
 *
 * L'arrêt n'est cherché **que dans** une tournée du fournisseur, relue sous
 * verrou avant lui. L'ordre de passage n'est pas imposé : sur le terrain, deux
 * arrêts voisins s'inversent. Rejouer une transition faite ne réécrit rien.
 */
final readonly class ExecuteProviderStop
{
    public const array ARRIVE_FROM = [TourStopStatus::PENDING];

    public const array COMPLETE_FROM = [TourStopStatus::ARRIVED, TourStopStatus::IN_PROGRESS];

    public const array SKIP_FROM = [TourStopStatus::PENDING, TourStopStatus::ARRIVED, TourStopStatus::IN_PROGRESS];

    public function __construct(
        private ProviderTourScope $tours,
        private UpdateTourStopAction $update,
        private WriteAuditLog $audit,
    ) {}

    public function arrive(string $tourId, string $stopId, AuditContext $context): TourStop
    {
        return $this->move($tourId, $stopId, self::ARRIVE_FROM, TourStopStatus::ARRIVED, ['actualArrivalAt' => now()], $context);
    }

    public function complete(string $tourId, string $stopId, AuditContext $context): TourStop
    {
        return $this->move($tourId, $stopId, self::COMPLETE_FROM, TourStopStatus::COMPLETED, ['actualDepartureAt' => now()], $context);
    }

    public function skip(string $tourId, string $stopId, string $reason, AuditContext $context): TourStop
    {
        return DB::transaction(function () use ($tourId, $stopId, $reason, $context): TourStop {
            $stop = $this->move($tourId, $stopId, self::SKIP_FROM, TourStopStatus::SKIPPED, [], $context, $skipped);

            if ($skipped) {
                $this->audit->execute($context->organizationId, $context->user, 'tour_stop.skipped', $stop, null, ['reason' => $reason], null, $context->ipAddress);
            }

            return $stop;
        });
    }

    /**
     * @param  list<TourStopStatus>  $from
     * @param  array<string, mixed>  $times
     */
    private function move(string $tourId, string $stopId, array $from, TourStopStatus $to, array $times, AuditContext $context, ?bool &$changed = null): TourStop
    {
        return DB::transaction(function () use ($tourId, $stopId, $from, $to, $times, $context, &$changed): TourStop {
            $tour = $this->tours->tour($tourId, lock: true);
            $stop = $tour->stops()->whereKey($stopId)->lockForUpdate()->first()
                ?? throw new NotFoundHttpException('Arrêt introuvable.');
            $changed = false;

            if ($stop->status === $to) {
                return $stop;
            }

            if ($tour->status !== TourStatus::IN_PROGRESS) {
                $this->fail('Démarrez la tournée avant de faire avancer ses arrêts.');
            }

            if ($tour->locked_by !== null) {
                $this->fail(ExecuteProviderTour::RESERVED);
            }

            if (! in_array($stop->status, $from, true)) {
                $this->fail(sprintf('Un arrêt « %s » ne peut pas passer à « %s ».', $stop->status->label(), $to->label()));
            }

            $changed = true;

            return $this->update->execute($stop, UpdateTourStopData::fromValidated(['status' => $to->value, ...$times]), $context);
        });
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['status' => [$message]]);
    }
}
