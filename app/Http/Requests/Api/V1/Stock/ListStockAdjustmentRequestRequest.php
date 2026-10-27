<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Stock;

use App\Modules\Stock\Enums\StockAdjustmentRequestStatus;
use App\Shared\Http\Requests\ListRequest;
use Closure;

/**
 * `status` accepte un code ou une liste (`status[]=pending&status[]=approved`).
 */
class ListStockAdjustmentRequestRequest extends ListRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $statuses = array_column(StockAdjustmentRequestStatus::cases(), 'value');

        return array_merge(parent::rules(), [
            'status' => ['sometimes', 'nullable', function (string $attribute, mixed $value, Closure $fail) use ($statuses): void {
                foreach ((array) $value as $code) {
                    if (! is_string($code) || ! in_array($code, $statuses, true)) {
                        $fail('Ce statut de demande est inconnu.');
                    }
                }
            }],
            'customerId' => ['sometimes', 'ulid'],
            'stockItemId' => ['sometimes', 'ulid'],
            'stockLocationId' => ['sometimes', 'ulid'],
        ]);
    }
}
