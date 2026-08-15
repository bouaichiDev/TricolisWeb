<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Tracking;

use App\Shared\Http\Requests\ListRequest;

/**
 * Filtres propres aux événements de suivi (§8).
 *
 * `organizationId` n'est pas accepté : la liste est toujours restreinte à
 * l'organisation active, portée par l'en-tête.
 */
class ListTrackingEventRequest extends ListRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'orderId' => ['sometimes', 'ulid'],
            'orderServiceId' => ['sometimes', 'ulid'],
            'tourId' => ['sometimes', 'ulid'],
            'tourStopId' => ['sometimes', 'ulid'],
            'eventType' => ['sometimes', 'string', 'max:64'],
            'createdBy' => ['sometimes', 'ulid'],
            'occurredFrom' => ['sometimes', 'date'],
            'occurredTo' => ['sometimes', 'date', 'after_or_equal:occurredFrom'],
        ]);
    }
}
