<?php

declare(strict_types=1);

namespace App\Modules\ProviderPortal\Actions;

use App\Modules\ProviderPortal\Services\ProviderTourScope;
use App\Modules\Tours\Actions\UpdateTourAction;
use App\Modules\Tours\DTOs\UpdateTourData;
use App\Modules\Tours\Enums\TourStatus;
use App\Modules\Tours\Enums\TourStopStatus;
use App\Modules\Tours\Models\Tour;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Démarrer et terminer une tournée attribuée au fournisseur (cycle encadré).
 *
 * ```
 * confirmed ─Démarrer─► in_progress ─Terminer─► completed
 * ```
 *
 * L'heure est celle du serveur. La tournée est relue sous verrou ; rejouer une
 * transition déjà faite rend l'état courant sans rien écrire — un double clic
 * ou une requête renvoyée après une coupure réseau n'ajoute rien. L'écriture
 * passe par `UpdateTourAction`, qui audite (`tour.status_changed`).
 */
final readonly class ExecuteProviderTour
{
    /** Un arrêt dans l'un de ces états n'empêche plus de terminer. */
    public const array CLOSED_STOPS = [TourStopStatus::COMPLETED, TourStopStatus::SKIPPED, TourStopStatus::CANCELLED];

    public function __construct(
        private ProviderTourScope $tours,
        private UpdateTourAction $update,
    ) {}

    public function start(string $tourId, AuditContext $context): Tour
    {
        return DB::transaction(function () use ($tourId, $context): Tour {
            $tour = $this->tours->tour($tourId, lock: true);

            if ($tour->status === TourStatus::IN_PROGRESS) {
                return $tour;
            }

            if ($tour->status !== TourStatus::CONFIRMED) {
                $this->fail('Seule une tournée confirmée peut démarrer.');
            }

            if ($tour->driver_id === null || $tour->vehicle_id === null) {
                $this->fail('Affectez un chauffeur et un véhicule avant de démarrer.');
            }

            return $this->update->execute($tour, UpdateTourData::fromValidated([
                'status' => TourStatus::IN_PROGRESS->value,
                'actualStartAt' => now(),
            ]), $context);
        });
    }

    public function complete(string $tourId, AuditContext $context): Tour
    {
        return DB::transaction(function () use ($tourId, $context): Tour {
            $tour = $this->tours->tour($tourId, lock: true);

            if ($tour->status === TourStatus::COMPLETED) {
                return $tour;
            }

            if ($tour->status !== TourStatus::IN_PROGRESS) {
                $this->fail('Seule une tournée en cours peut être terminée.');
            }

            $open = $tour->stops()
                ->whereNotIn('status', array_map(static fn (TourStopStatus $status): string => $status->value, self::CLOSED_STOPS))
                ->lockForUpdate()
                ->exists();

            if ($open) {
                $this->fail('Des arrêts ne sont ni terminés ni passés : terminez-les d’abord.');
            }

            return $this->update->execute($tour, UpdateTourData::fromValidated([
                'status' => TourStatus::COMPLETED->value,
                'actualEndAt' => now(),
            ]), $context);
        });
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['status' => [$message]]);
    }
}
