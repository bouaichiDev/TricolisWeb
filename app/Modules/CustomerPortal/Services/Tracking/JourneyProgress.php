<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Services\Tracking;

use App\Modules\Orders\Models\OrderService;
use App\Modules\Tracking\Models\TrackingEventDefinition;
use Illuminate\Support\Collection;

/**
 * Progression d'une prestation dans le parcours configuré par le transporteur.
 *
 * Même règle que l'écran interne (`buildJourney`) : une étape vaut pour la
 * prestation si elle ne vise aucun service ou vise le sien, et elle est
 * franchie par un événement de même code — venu de **cette** prestation quand
 * l'étape en nomme une. Sans parcours configuré, pas de pourcentage : `null`,
 * plutôt qu'un chiffre sans fondement.
 */
final class JourneyProgress
{
    /**
     * @param  Collection<string, TrackingEventDefinition>  $definitions
     * @param  Collection<int, array{code: string, orderServiceId: string|null}>  $events
     * @return array{reached: int, total: int, percent: int}|null
     */
    public static function of(OrderService $service, Collection $definitions, Collection $events): ?array
    {
        $steps = $definitions->filter(static fn (TrackingEventDefinition $definition): bool => $definition->active
            && ($definition->service_id === null || $definition->service_id === $service->service_id));

        if ($steps->isEmpty()) {
            return null;
        }

        $reached = $steps->filter(static fn (TrackingEventDefinition $definition): bool => $events->contains(
            static fn (array $event): bool => $event['code'] === $definition->code
                && ($definition->service_id === null || $event['orderServiceId'] === $service->id),
        ))->count();

        return [
            'reached' => $reached,
            'total' => $steps->count(),
            'percent' => (int) round($reached * 100 / $steps->count()),
        ];
    }
}
