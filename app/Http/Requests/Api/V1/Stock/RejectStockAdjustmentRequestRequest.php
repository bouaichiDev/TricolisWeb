<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Stock;

use Illuminate\Foundation\Http\FormRequest;

/**
 * La réponse est obligatoire : un refus sans motif laisserait le client sans
 * rien à corriger.
 */
class RejectStockAdjustmentRequestRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'reviewComment' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }
}
