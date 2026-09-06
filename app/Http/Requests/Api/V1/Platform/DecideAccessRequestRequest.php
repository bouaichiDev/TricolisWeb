<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Platform;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Le motif qui accompagne une décision.
 *
 * Facultatif à l'acceptation — le résultat parle de lui-même —, il est ce qui
 * rend un refus relisible six mois plus tard.
 */
class DecideAccessRequestRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
