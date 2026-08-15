<?php

declare(strict_types=1);

namespace App\Modules\Exports\DTOs;

use App\Shared\Support\PartialAttributes;
use App\Shared\Support\Secret;

/**
 * Modification partielle d'une configuration d'export.
 *
 * **Le mot de passe suit une règle à trois branches**, comme le §20 le demande :
 *
 * | Payload | Effet |
 * |---|---|
 * | `password` absent | l'ancien est **conservé** |
 * | `password` non vide | il **remplace** l'ancien, chiffré |
 * | `password: null` | il est **effacé** — geste explicite |
 *
 * Sans la première branche, toute modification d'un autre champ effacerait
 * silencieusement le mot de passe de transport.
 */
final readonly class UpdateExportConfigurationData
{
    /** @var array<string, string> */
    private const array MAPPING = [
        'name' => 'name',
        'export_type' => 'exportType',
        'format' => 'format',
        'transport' => 'transport',
        'host' => 'host',
        'port' => 'port',
        'username' => 'username',
        'remote_directory' => 'remoteDirectory',
        'file_name_pattern' => 'fileNamePattern',
        'encoding' => 'encoding',
        'frequency' => 'frequency',
        'settings' => 'settings',
        'is_active' => 'isActive',
    ];

    public function __construct(public PartialAttributes $attributes) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        $attributes = PartialAttributes::fromValidated($validated, self::MAPPING);

        if (! array_key_exists('password', $validated)) {
            return new self($attributes);
        }

        return new self($attributes->with('encrypted_password', Secret::encrypt($validated['password'])));
    }
}
