<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Queries;

use App\Http\Requests\Api\V1\Tracking\ListTrackingEventRequest;
use App\Modules\Tracking\Models\TrackingEvent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Recherche paginée des événements de suivi.
 *
 * **Unique point de construction de la requête**, pour les sept routes de
 * lecture : la liste globale et les six consultations imbriquées. Le §8
 * interdit de dupliquer la logique — les routes imbriquées passent simplement un
 * filtre supplémentaire par `$scoped`.
 */
final readonly class TrackingEventListQuery
{
    /** @var list<string> */
    private const array SORTABLE = ['occurred_at', 'event_type', 'status'];

    /**
     * @param  array<string, string>  $scoped  filtres imposés par la route imbriquée
     */
    public function paginate(ListTrackingEventRequest $request, string $organizationId, array $scoped = []): LengthAwarePaginator
    {
        $query = TrackingEvent::inOrganization($organizationId);

        foreach ($scoped as $column => $value) {
            $query->where($column, $value);
        }

        if ($request->filled('search')) {
            $search = $request->validated('search');
            $query->where(fn ($builder) => $builder
                ->where('description', 'like', "%{$search}%")
                ->orWhere('event_type', 'like', "%{$search}%")
                ->orWhere('status', 'like', "%{$search}%"));
        }

        foreach ([
            'orderId' => 'order_id',
            'orderServiceId' => 'order_service_id',
            'tourId' => 'tour_id',
            'tourStopId' => 'tour_stop_id',
            'eventType' => 'event_type',
            'status' => 'status',
            'createdBy' => 'created_by',
        ] as $input => $column) {
            if ($request->filled($input) && ! array_key_exists($column, $scoped)) {
                $query->where($column, $request->validated($input));
            }
        }

        if ($request->filled('occurredFrom')) {
            $query->where('occurred_at', '>=', $request->validated('occurredFrom'));
        }

        if ($request->filled('occurredTo')) {
            $query->where('occurred_at', '<=', $request->validated('occurredTo'));
        }

        // Ordre par defaut : du plus recent au plus ancien, comme le §8 le pose.
        $sort = $request->getSort('occurred_at', self::SORTABLE);
        $direction = $request->validated('direction') ?? ($sort === 'occurred_at' ? 'desc' : 'asc');

        return $query->orderBy($sort, $direction)->paginate($request->getPerPage());
    }
}
