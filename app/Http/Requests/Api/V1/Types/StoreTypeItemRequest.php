<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Types;

use App\Modules\Types\Models\Type;
use App\Shared\Http\Rules\BelongsToActiveOrganization;
use App\Shared\Http\Rules\ExistsInStatusReferential;
use Illuminate\Foundation\Http\FormRequest;

class StoreTypeItemRequest extends FormRequest
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
        return [
            'typeId' => [
                'required',
                'string',
                new BelongsToActiveOrganization(Type::class, null, 'Cette source n’appartient pas à l’organisation active.'),
            ],
            'code' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'max:32', new ExistsInStatusReferential('type_item')],
            'position' => ['sometimes', 'integer', 'min:0', 'max:65535'],
        ];
    }
}
