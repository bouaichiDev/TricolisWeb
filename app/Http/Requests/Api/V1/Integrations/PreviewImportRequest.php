<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Integrations;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Le fichier soumis à une prévisualisation.
 *
 * Il n'est **jamais stocké** : lu en mémoire, puis oublié. La limite de taille
 * en découle — on éprouve une correspondance sur un échantillon, pas sur un lot
 * de production, et deux mégaoctets valent déjà des milliers de lignes.
 *
 * Aucune extension n'est imposée : le format vient de `fileFormat`, une chaîne
 * libre de la configuration, et un client peut nommer son export `.txt` tout en
 * y mettant du CSV.
 */
final class PreviewImportRequest extends FormRequest
{
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
            'file' => ['required', 'file', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Choisissez un fichier d’exemple à éprouver.',
            'file.max' => 'Un échantillon de 2 Mo suffit à éprouver une correspondance.',
        ];
    }
}
