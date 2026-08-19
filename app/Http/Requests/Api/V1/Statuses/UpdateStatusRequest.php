<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Statuses;

use App\Modules\Statuses\Models\Status;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Modification d'un statut du référentiel.
 *
 * `source` n'est pas modifiable : un statut change d'entité en étant recréé,
 * pas en glissant d'un domaine à l'autre — les enregistrements qui portent déjà
 * son code suivraient sinon en silence.
 */
class UpdateStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Status $status */
        $status = $this->route('status');

        return [
            'status' => [
                'sometimes',
                'integer',
                'min:0',
                Rule::unique('statuses', 'status')->where('source', $status->source)->ignore($status->id),
            ],
            'code' => [
                'sometimes',
                'string',
                'max:64',
                Rule::unique('statuses', 'code')->where('source', $status->source)->ignore($status->id),
            ],
            'label' => ['sometimes', 'string', 'max:255'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:64'],
            'active' => ['sometimes', 'boolean'],
            'isToSend' => ['sometimes', 'boolean'],
            'position' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
