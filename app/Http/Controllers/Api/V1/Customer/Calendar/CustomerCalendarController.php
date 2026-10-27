<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer\Calendar;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\Calendar\CalendarOrdersRequest;
use App\Modules\CustomerPortal\Services\CurrentCustomerContext;
use App\Modules\CustomerPortal\Services\CustomerCalendarEvents;
use App\Modules\Orders\Models\Service;
use App\Modules\Statuses\Services\StatusMachine;
use App\Shared\Database\MorphMap;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Calendrier des commandes du Customer courant (§6).
 *
 * Permission : `customer.calendar.view`. Le client vient du jeton ; la période
 * et les filtres, de la requête. Les options de filtre — statuts du
 * référentiel, services du transporteur — accompagnent la réponse : le
 * calendrier n'a pas à dépendre de la permission de créer des commandes pour
 * les obtenir.
 */
class CustomerCalendarController extends Controller
{
    public function __invoke(
        CalendarOrdersRequest $request,
        CurrentCustomerContext $context,
        CustomerCalendarEvents $events,
        StatusMachine $statuses,
    ): JsonResponse {
        $customer = $context->customer();
        $filters = $request->validated();
        $result = $events->for($customer, $filters);

        return ApiResponse::ok($result['events'], [
            'from' => $filters['from'],
            'to' => $filters['to'],
            'count' => count($result['events']),
            'truncated' => $result['truncated'],
            'filters' => [
                'statuses' => $statuses->statuses(MorphMap::ORDER)->where('active', true)
                    ->map(static fn ($status): array => ['code' => $status->code, 'label' => $status->label])->values(),
                'services' => Service::where('organization_id', $customer->organization_id)->where('status', 'active')
                    ->orderBy('name')->get(['id', 'code', 'name'])
                    ->map(static fn (Service $service): array => ['id' => $service->id, 'code' => $service->code, 'name' => $service->name])
                    ->values(),
            ],
        ]);
    }
}
