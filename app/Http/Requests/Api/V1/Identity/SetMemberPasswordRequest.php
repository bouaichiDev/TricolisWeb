<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Identity;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password as PasswordRule;

/**
 * Un mot de passe posé par l'administrateur.
 *
 * **Les mêmes exigences que la réinitialisation publique.** Un mot de passe
 * choisi par un tiers n'a aucune raison d'être plus faible que celui qu'on
 * choisit soi-même — c'est même l'inverse, puisqu'il transite par un canal que
 * personne ne maîtrise : un message, un appel, un papier.
 *
 * `confirmed` protège de la faute de frappe : l'administrateur ne relira pas le
 * mot de passe une fois posé, et une coquille enfermerait le membre dehors.
 */
class SetMemberPasswordRequest extends FormRequest
{
    /** L'autorisation est tranchée par le contrôleur, comme ailleurs ici. */
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
            'password' => ['required', 'string', 'confirmed', PasswordRule::defaults()],
        ];
    }
}
