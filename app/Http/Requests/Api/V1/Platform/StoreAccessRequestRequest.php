<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Platform;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Le formulaire public de demande d'accès.
 *
 * Quatre champs obligatoires, et le téléphone en fait partie : c'est par lui
 * que l'administrateur vérifie qu'une société existe vraiment avant de lui
 * ouvrir un back-office. Une adresse de courriel ne prouve rien — elle se crée
 * en trente secondes.
 *
 * **Une seule demande en attente par adresse.** Sans cette règle, un formulaire
 * renvoyé trois fois par impatience remplirait l'écran de la plateforme de
 * doublons, dont deux seraient refusés sans qu'on sache pourquoi.
 */
class StoreAccessRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'companyName' => ['required', 'string', 'max:255'],
            'contactName' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('access_requests', 'email')->where('status', 'pending'),
            ],
            'phone' => ['required', 'string', 'max:40'],
            'message' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'Une demande est déjà en cours pour cette adresse.',
        ];
    }
}
