<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Provider\Resources;

use App\Http\Requests\Api\V1\Provider\ProviderServerControlledFields;
use App\Modules\ProviderPortal\Services\CurrentProviderContext;
use App\Modules\ProviderPortal\Services\ProviderResourceScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Modification d'un chauffeur : code, identité, téléphone. L'e-mail, qui
 * identifie le compte du chauffeur, ne change pas ici ; le statut a ses routes.
 */
class UpdateProviderDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Le chauffeur est résolu dans le périmètre **avant** la validation : sans
     * cela, un identifiant étranger confronté aux codes pris dirait à qui
     * appartient quel code. Étranger ou inexistant : 404.
     */
    protected function prepareForValidation(): void
    {
        app(ProviderResourceScope::class)->driver((string) $this->route('driver'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $organizationId = app(CurrentProviderContext::class)->organizationId();

        return [
            ...ProviderServerControlledFields::prohibit([...StoreProviderDriverRequest::PROHIBITED, 'email']),
            'code' => [
                'sometimes', 'string', 'max:64',
                Rule::unique('drivers', 'code')->where('organization_id', $organizationId)->ignore((string) $this->route('driver')),
            ],
            'firstName' => ['sometimes', 'string', 'max:255'],
            'lastName' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['code.unique' => 'Ce code ne peut pas être utilisé.'];
    }
}
