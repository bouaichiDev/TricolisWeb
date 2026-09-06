<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Platform;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Dépôt du logo par défaut de la plateforme.
 *
 * Les mêmes règles que pour le logo d'une organisation, et **ce n'est pas une
 * copie paresseuse** : ce logo ne descend jamais sur une facture, donc la
 * contrainte du moteur PDF ne le vise pas directement. Elle le vise
 * indirectement — un intégrateur qui pose ici son image de marque la posera
 * demain sur ses organisations, et lui laisser déposer un SVG ou un WebP ici
 * pour le lui refuser là serait la meilleure façon de faire croire à un bug.
 *
 * `mimes` **et** `image` : le premier regarde l'extension, le second demande à
 * la bibliothèque d'images de reconnaître le contenu. Un exécutable renommé en
 * `.png` passe le premier et échoue au second.
 */
class StorePlatformLogoRequest extends FormRequest
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
            'logo' => ['required', 'file', 'image', 'mimes:png,jpg,jpeg,gif', 'max:1024'],
        ];
    }
}
