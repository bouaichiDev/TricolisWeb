<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Integrations;

use App\Modules\Integrations\Services\Sources\RestPullSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * La connexion REST d'un import en pull.
 *
 * `baseUrl` est en **HTTPS** : un secret envoyé en clair n'en est plus un.
 * `credentials` est en écriture seule — absent, le secret existant est gardé ;
 * `null`, il est effacé. `Authorization` est refusé dans les en-têtes libres :
 * l'authentification se construit depuis le secret chiffré, jamais depuis un
 * texte stocké en clair.
 */
class SaveRestPullConnectionRequest extends FormRequest
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
        $name = ['sometimes', 'nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_.\-\[\]]+$/'];

        return [
            'baseUrl' => ['required', 'url:https', 'max:512'],
            'authType' => ['required', Rule::in(['none', 'bearer', 'api_key', 'basic'])],
            'credentials' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'headers' => ['sometimes', 'nullable', 'array', 'max:20'],
            'headers.*' => ['string', 'max:512'],
            'timeoutSeconds' => ['sometimes', 'integer', 'min:1', 'max:120'],
            'isActive' => ['sometimes', 'boolean'],
            'settings' => ['sometimes', 'nullable', 'array'],
            'settings.path' => ['sometimes', 'nullable', 'string', 'max:512'],
            'settings.healthPath' => ['sometimes', 'nullable', 'string', 'max:512'],
            'settings.recordsPath' => ['sometimes', 'nullable', 'string', 'max:128'],
            'settings.query' => ['sometimes', 'nullable', 'array', 'max:20'],
            'settings.query.*' => ['nullable', 'scalar'],
            'settings.since' => ['sometimes', 'nullable', 'array'],
            'settings.since.param' => $name,
            'settings.since.format' => ['sometimes', 'nullable', 'string', 'max:32'],
            'settings.pagination' => ['sometimes', 'nullable', 'array'],
            'settings.pagination.type' => ['sometimes', Rule::in(RestPullSettings::PAGINATION_TYPES)],
            'settings.pagination.param' => $name,
            'settings.pagination.sizeParam' => $name,
            'settings.pagination.size' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'settings.pagination.start' => ['sometimes', 'integer', 'min:0'],
            'settings.pagination.cursorPath' => ['sometimes', 'nullable', 'string', 'max:128'],
            'settings.pagination.maxPages' => ['sometimes', 'integer', 'min:1', 'max:'.(int) config('customer_integrations.pull.max_pages', 50)],
            'settings.actorUserId' => ['sometimes', 'nullable', 'string', 'ulid', 'exists:users,id'],
            'settings.apiKeyHeader' => ['sometimes', 'nullable', 'string', 'regex:/^[A-Za-z0-9-]{1,64}$/', 'not_in:Authorization,authorization'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function after(): array
    {
        return [function ($validator): void {
            foreach (array_keys((array) $this->input('headers', [])) as $header) {
                if (strcasecmp((string) $header, 'Authorization') === 0) {
                    $validator->errors()->add('headers', 'L’en-tête Authorization se construit depuis le secret : il ne se saisit pas.');
                }
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['baseUrl.url' => 'L’adresse doit être en HTTPS : un secret envoyé en clair n’en est plus un.'];
    }
}
