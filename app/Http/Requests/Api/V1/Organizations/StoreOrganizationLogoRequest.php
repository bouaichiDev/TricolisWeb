<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Organizations;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Dépôt du logo d'une organisation.
 *
 * **Les formats sont ceux que dompdf sait poser sur du papier** : PNG, JPEG et
 * GIF. Le SVG en est absent bien qu'il soit le format naturel d'un logo — le
 * moteur ne le rend pas, et l'accepter donnerait une facture au logo manquant
 * sans qu'aucune erreur ne soit levée. Le WebP en est absent pour la même
 * raison.
 *
 * `mimes` **et** `image` : le premier regarde l'extension, le second demande à
 * la bibliothèque d'images de reconnaître le contenu. Un exécutable renommé en
 * `.png` passe le premier et échoue au second.
 *
 * Un mégaoctet suffit largement à un logo, et le fichier part **entier** dans
 * chaque facture PDF : une image de dix mégaoctets ferait des factures de
 * treize.
 */
class StoreOrganizationLogoRequest extends FormRequest
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
