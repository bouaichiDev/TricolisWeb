<?php

declare(strict_types=1);

namespace App\Modules\Exports\Enums;

/**
 * Moyen d'acheminement d'un export.
 *
 * Exactement les cinq valeurs du diagramme (lignes 77-83). `FTPS`, `HTTP`,
 * `HTTPS`, `S3`, `WEBDAV` et `LOCAL` ne sont pas ajoutés.
 */
enum ExportTransport: string
{
    case FTP = 'ftp';
    case SFTP = 'sftp';
    case REST_API = 'rest_api';
    case EMAIL = 'email';
    case MANUAL = 'manual';

    /**
     * Ce transport exige-t-il un hôte distant ?
     *
     * Seul `host` est rendu conditionnellement obligatoire : le §19 dit que les
     * autres champs de connexion « peuvent » être nécessaires — une connexion
     * anonyme, sur port par défaut, à la racine, reste un cas réel.
     */
    public function requiresHost(): bool
    {
        return in_array($this, [self::FTP, self::SFTP, self::REST_API], true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
