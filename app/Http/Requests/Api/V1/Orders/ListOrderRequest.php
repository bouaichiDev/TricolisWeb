<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Orders;

use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Enums\OrderStatus;
use App\Shared\Http\Requests\ListRequest;
use Illuminate\Validation\Rule;

/**
 * Filtres propres aux commandes, en plus des filtres de liste communs.
 */
class ListOrderRequest extends ListRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'status' => ['sometimes', Rule::enum(OrderStatus::class)],
            'source' => ['sometimes', Rule::enum(OrderSource::class)],
            'customerId' => ['sometimes', 'ulid'],
            'agencyId' => ['sometimes', 'ulid'],
            'depotId' => ['sometimes', 'ulid'],
            'orderType' => ['sometimes', 'string', 'max:64'],
            'requestedDate' => ['sometimes', 'date'],
            'city' => ['sometimes', 'string', 'max:255'],
            'fromCatalog' => ['sometimes', 'boolean'],
            // Les commandes importees arrivent sans depot : c'est ce qui reste
            // a faire dessus, et donc ce qu'on vient chercher dans la liste.
            'withoutDepot' => ['sometimes', 'boolean'],
        ]);
    }
}
