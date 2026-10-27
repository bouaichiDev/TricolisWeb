<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Integrations;

use App\Modules\Integrations\DTOs\InboundFileConnection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * La connexion FTP/SFTP d'une relève automatique.
 *
 * Le protocole n'est pas saisi : il découle du type de source de la
 * configuration. `password` est en écriture seule — absent, le mot de passe
 * existant est gardé ; `null`, il est effacé. L'hôte (pas d'adresse interne)
 * et les chemins (pas de remontée) sont contrôlés par `InboundFileConnections`.
 */
class SaveFileConnectionRequest extends FormRequest
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
        $path = ['sometimes', 'nullable', 'string', 'max:255', 'not_regex:/\.\./'];

        return [
            'host' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9.\-]+$/'],
            'port' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:65535'],
            'username' => ['sometimes', 'nullable', 'string', 'max:255'],
            'password' => ['sometimes', 'nullable', 'string', 'max:1024'],
            'directory' => $path,
            'filePattern' => ['sometimes', 'nullable', 'string', 'regex:/^[A-Za-z0-9._*?\-]{1,64}$/'],
            'afterImport' => ['sometimes', Rule::in(InboundFileConnection::AFTER_IMPORT)],
            'archiveDirectory' => $path,
            'actorEmail' => ['sometimes', 'nullable', 'email', 'max:255', 'exists:users,email'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'host.regex' => 'L’hôte est un nom de domaine ou une adresse, sans protocole ni chemin.',
            'directory.not_regex' => 'Le répertoire ne peut pas remonter l’arborescence.',
            'archiveDirectory.not_regex' => 'Le répertoire d’archive ne peut pas remonter l’arborescence.',
            'filePattern.regex' => 'Le motif est un nom de fichier (lettres, chiffres, . _ - * ?), jamais un chemin.',
            'actorEmail.exists' => 'Aucun utilisateur ne porte cet email.',
        ];
    }
}
