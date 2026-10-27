<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Services\Tracking;

use App\Modules\Orders\Models\Order;
use App\Modules\ProofOfDelivery\Models\ProofOfDelivery;
use Illuminate\Support\Collection;

/**
 * L'état de suivi d'une commande à un instant donné.
 *
 * Il ne contient que ce qui touche la commande : ses prestations, les arrêts
 * qui les portent, les événements et preuves qui la nomment. Rien de la
 * tournée au-delà — c'est la garantie qu'aucun présentateur ne peut laisser
 * fuir l'arrêt d'un autre client : il ne l'a jamais eu entre les mains.
 */
final readonly class OrderTrackingSnapshot
{
    /**
     * @param  list<TrackedService>  $services
     * @param  Collection<int, array{status: string|null, at: string|null}>  $statusChanges
     * @param  Collection<int, array{code: string, title: string, description: string|null, at: string|null, orderServiceId: string|null}>  $journeyEvents
     * @param  Collection<int, ProofOfDelivery>  $proofs
     */
    public function __construct(
        public Order $order,
        public bool $available,
        public bool $active,
        public array $services,
        public Collection $statusChanges,
        public Collection $journeyEvents,
        public Collection $proofs,
    ) {}

    public function service(string $orderServiceId): ?TrackedService
    {
        foreach ($this->services as $tracked) {
            if ($tracked->service->id === $orderServiceId) {
                return $tracked;
            }
        }

        return null;
    }

    /**
     * Part des prestations effectuées, annulées exclues.
     *
     * @return array{percent: int|null, completedServices: int, totalServices: int}
     */
    public function progress(): array
    {
        $counted = array_values(array_filter($this->services, static fn (TrackedService $tracked): bool => ! $tracked->cancelled()));
        $completed = count(array_filter($counted, static fn (TrackedService $tracked): bool => $tracked->completed()));
        $total = count($counted);

        return [
            'percent' => $total === 0 ? null : (int) round($completed * 100 / $total),
            'completedServices' => $completed,
            'totalServices' => $total,
        ];
    }

    /**
     * La plus proche ETA disponible parmi les prestations.
     *
     * @return array{available: bool, estimatedAt: string|null, source: string|null, orderServiceId: string|null}
     */
    public function eta(): array
    {
        $candidates = array_filter($this->services, static fn (TrackedService $tracked): bool => $tracked->eta['available']);
        usort($candidates, static fn (TrackedService $a, TrackedService $b): int => strcmp((string) $a->eta['estimatedAt'], (string) $b->eta['estimatedAt']));
        $first = $candidates[0] ?? null;

        return $first === null
            ? [...TrackingEta::unavailable(), 'orderServiceId' => null]
            : [...$first->eta, 'orderServiceId' => $first->service->id];
    }

    /**
     * @return array{latitude: float, longitude: float, recordedAt: string|null, orderServiceId: string}|null
     */
    public function position(): ?array
    {
        foreach ($this->services as $tracked) {
            if ($tracked->position !== null) {
                return [...$tracked->position, 'orderServiceId' => $tracked->service->id];
            }
        }

        return null;
    }
}
