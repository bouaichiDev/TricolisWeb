<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Customer\Claims;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Pièce jointe d'une réclamation (§23) : les mêmes formats et la même taille
 * que les documents de commande du portail — aucun exécutable, 10 Mo.
 */
class StoreCustomerClaimDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,csv,txt'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Choisissez un fichier.',
            'file.max' => 'Le fichier dépasse 10 Mo.',
            'file.mimes' => 'Formats acceptés : PDF, images JPG/PNG, Word, Excel, CSV, texte.',
        ];
    }
}
