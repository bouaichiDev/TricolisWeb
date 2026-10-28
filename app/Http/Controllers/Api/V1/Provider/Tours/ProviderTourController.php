<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Provider\Tours;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Provider\Tours\ListProviderToursRequest;
use App\Http\Requests\Api\V1\Provider\Tours\ProviderCalendarRequest;
use App\Http\Resources\Api\V1\Provider\ProviderTourResource;
use App\Modules\ProviderPortal\Services\ProviderTourScope;
use App\Modules\Tours\Enums\TourStatus;
use App\Modules\Tours\Models\Tour;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Tournées attribuées au fournisseur courant : liste, détail, calendrier.
 * Lecture seule ; le planificateur interne garde la main sur leur structure.
 */
class ProviderTourController extends Controller
{
    private const array SUMMARY = ['agency:id,name', 'depot:id,name', 'driver:id,code,name', 'vehicle:id,code,registration_number'];

    public function __construct(private readonly ProviderTourScope $scope) {}

    public function index(ListProviderToursRequest $request): JsonResponse
    {
        $search = $request->validated('search');

        $tours = $this->scope->tours()
            ->with(self::SUMMARY)
            ->when($request->validated('from'), static fn ($query, string $from) => $query->whereDate('tour_date', '>=', $from))
            ->when($request->validated('to'), static fn ($query, string $to) => $query->whereDate('tour_date', '<=', $to))
            ->when($request->validated('status'), static fn ($query, string $status) => $query->where('status', $status))
            ->when($request->validated('state'), static fn ($query, string $state) => $query->whereIn('status', array_map(
                static fn (TourStatus $status): string => $status->value,
                ProviderTourScope::STATES[$state],
            )))
            ->when($search, static fn ($query) => $query->where('tour_number', 'like', '%'.addcslashes((string) $search, '%_\\').'%'))
            ->orderBy('tour_date')->orderBy('planned_start_at')
            ->paginate((int) ($request->validated('perPage') ?? 25));

        return ApiResponse::paginated($tours->through(static fn (Tour $tour) => (new ProviderTourResource($tour))->resolve()));
    }

    public function show(string $tour): JsonResponse
    {
        $model = $this->scope->tour($tour)->load([
            ...self::SUMMARY,
            'stops' => static fn ($query) => $query->orderBy('sequence'),
            'stops.address',
            'stops.services.orderService.service:id,name',
            'stops.services.orderService.contacts',
        ]);
        // Chaque arrêt sait dans quelle tournée il est : ses actions en dépendent.
        $model->stops->each->setRelation('tour', $model);

        return ApiResponse::ok(new ProviderTourResource($model));
    }

    /**
     * Événements de calendrier : une tournée par événement, de son début à sa
     * fin prévus (sa journée entière à défaut).
     */
    public function calendar(ProviderCalendarRequest $request): JsonResponse
    {
        $tours = $this->scope->tours()
            ->whereDate('tour_date', '>=', $request->validated('from'))
            ->whereDate('tour_date', '<=', $request->validated('to'))
            ->orderBy('tour_date')
            ->limit(500)
            ->get();

        return ApiResponse::ok($tours->map(static fn (Tour $tour): array => [
            'id' => $tour->id,
            'title' => $tour->tour_number,
            'start' => $tour->planned_start_at?->toIso8601String() ?? $tour->tour_date->toDateString(),
            'end' => $tour->planned_end_at?->toIso8601String(),
            'allDay' => $tour->planned_start_at === null,
            'status' => $tour->status instanceof TourStatus ? $tour->status->value : (string) $tour->status,
        ])->values());
    }
}
