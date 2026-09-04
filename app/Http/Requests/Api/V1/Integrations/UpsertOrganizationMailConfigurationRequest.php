<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Integrations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Réglage de la boîte d'envoi de l'organisation.
 *
 * **Le mot de passe est facultatif à la mise à jour**, et son absence ne
 * l'efface pas : rouvrir l'écran pour changer un port ne doit pas obliger à
 * ressaisir un secret qu'on n'a plus sous la main. Pour l'effacer, il faut le
 * dire — une chaîne vide explicite.
 */
class UpsertOrganizationMailConfigurationRequest extends FormRequest
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
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            // Nul vaut « aucun chiffrement ». Les serveurs distinguent `tls`,
            // négocié après la connexion, de `ssl`, chiffré d'emblée.
            'encryption' => ['nullable', Rule::in(['tls', 'ssl'])],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['sometimes', 'nullable', 'string', 'max:255'],
            // L'identité affichée, distincte de l'authentification : on
            // s'authentifie avec un compte technique et l'on signe avec
            // l'adresse du service client.
            'fromAddress' => ['required', 'email', 'max:255'],
            'fromName' => ['nullable', 'string', 'max:255'],
            'replyTo' => ['nullable', 'email', 'max:255'],
            'isActive' => ['sometimes', 'boolean'],
        ];
    }
}
