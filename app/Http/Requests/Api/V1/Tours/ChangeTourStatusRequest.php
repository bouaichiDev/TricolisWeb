<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Tours;

use App\Shared\Http\Rules\ExistsInStatusReferential;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Passage d'une tournée d'un état à un autre.
 *
 * Le code doit exister au référentiel ; c'est `status_transitions` qui dira
 * ensuite si le passage depuis l'état courant est permis. Deux contrôles
 * distincts : l'un sur le vocabulaire, l'autre sur l'enchaînement.
 */
class ChangeTourStatusRequest extends FormRequest
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
            'status' => ['required', 'string', 'max:32', new ExistsInStatusReferential('tour')],
        ];
    }
}
