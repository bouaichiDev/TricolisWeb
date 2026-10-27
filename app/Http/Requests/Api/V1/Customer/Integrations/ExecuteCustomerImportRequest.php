<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Customer\Integrations;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Confirmation d'un import prévisualisé : la configuration et le jeton rendu
 * par la prévisualisation. Aucun fichier n'est redéposé — c'est celui qui a
 * été prévisualisé qui s'importe.
 */
class ExecuteCustomerImportRequest extends FormRequest
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
            'customerId' => ['prohibited'],
            'configurationId' => ['required', 'string', 'ulid'],
            'token' => ['required', 'string', 'size:48', 'alpha_num'],
        ];
    }
}
