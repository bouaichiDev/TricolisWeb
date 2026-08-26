<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Planning;

use App\Shared\Http\Requests\ListRequest;
use Illuminate\Contracts\Validation\ValidationRule;

/** Filtres du pool « à planifier ». */
class ListPlanningPoolRequest extends ListRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'requestedDate' => ['sometimes', 'date'],
            'customerId' => ['sometimes', 'ulid'],
            'agencyId' => ['sometimes', 'ulid'],
        ]);
    }
}
