<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Tours;

use App\Modules\Tours\Models\Tour;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Tournée réduite à ce qu'affiche une liste déroulante ou un rappel.
 *
 * @mixin Tour
 */
class TourCompactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tourNumber' => $this->tour_number,
            'tourDate' => $this->tour_date?->toDateString(),
            'status' => $this->status->value,
        ];
    }
}
