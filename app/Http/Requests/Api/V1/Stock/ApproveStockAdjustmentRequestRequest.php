<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Stock;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `stockLocationId` n'est exigé que pour une demande « tout l'article », et
 * refusé sinon : cette règle dépend de la demande, elle est tenue par
 * `ApproveStockAdjustmentRequestAction` — après le contrôle d'organisation,
 * pour qu'une demande d'ailleurs réponde 404 et non 422.
 */
class ApproveStockAdjustmentRequestRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'stockLocationId' => ['sometimes', 'nullable', 'ulid'],
            'reviewComment' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
