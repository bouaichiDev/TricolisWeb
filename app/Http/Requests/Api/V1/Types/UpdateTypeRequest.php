<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Types;

use App\Shared\Http\Rules\ExistsInStatusReferential;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTypeRequest extends FormRequest
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
            'code' => ['sometimes', 'string', 'max:64'],
            'name' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'max:32', new ExistsInStatusReferential('type')],
        ];
    }
}
