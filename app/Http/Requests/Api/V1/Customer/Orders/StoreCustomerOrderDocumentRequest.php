<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Customer\Orders;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Mêmes garde-fous que l'upload interne (taille, extensions) ; le type et le
 * statut du document sont imposés par le portail.
 */
class StoreCustomerOrderDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,csv,txt'],
            'referenceNumber' => ['nullable', 'string', 'max:255'],
        ];
    }
}
