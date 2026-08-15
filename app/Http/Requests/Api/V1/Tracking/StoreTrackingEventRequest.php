<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Tracking;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Création d'un événement de suivi.
 *
 * `organizationId` et `createdBy` ne sont **pas** acceptés : le premier est
 * forcé à celui de la commande, le second est l'utilisateur authentifié. Les
 * accepter permettrait de les contredire.
 *
 * Les contrôles d'appartenance (service dans la commande, arrêt dans la
 * tournée) relèvent de `TrackingScopeGuard` : ils traversent plusieurs
 * relations que la Request n'a pas à charger.
 */
class StoreTrackingEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'orderId' => ['required', 'ulid'],
            'orderServiceId' => ['nullable', 'ulid'],
            'tourId' => ['nullable', 'ulid'],
            'tourStopId' => ['nullable', 'ulid'],
            'eventType' => ['required', 'string', 'max:64'],
            'status' => ['required', 'string', 'max:32'],
            'description' => ['nullable', 'string'],
            // Bornes techniques, pas des enums metier.
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'occurredAt' => ['required', 'date'],
        ];
    }
}
