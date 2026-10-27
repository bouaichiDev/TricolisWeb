<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\Sources;

use App\Modules\Integrations\Models\CustomerImportConfiguration;
use App\Modules\Integrations\Services\Import\Parsers\PayloadParsers;
use App\Modules\Orders\Enums\OrderSource;

/**
 * Le canal d'une configuration d'import, lu dans `sourceType`.
 *
 * `sourceType` reste une **chaîne libre** (le diagramme n'en énumère aucune
 * valeur) : la lecture est tolérante et ne crée aucun enum en base. Elle
 * classe seulement :
 *
 * - `rest_pull` — `rest`, `api`, `http` : Tricolis appelle l'API du client ;
 * - `sftp` / `ftp` — Tricolis relève les fichiers déposés ;
 * - `upload` — tout le reste : un fichier déposé par un utilisateur.
 */
final class ImportSourceKind
{
    public const string REST_PULL = 'rest_pull';

    public const string SFTP = 'sftp';

    public const string FTP = 'ftp';

    public const string UPLOAD = 'upload';

    public static function of(CustomerImportConfiguration $configuration): string
    {
        $type = strtolower(trim((string) $configuration->source_type));

        return match (true) {
            str_contains($type, 'rest'), str_contains($type, 'api'), str_contains($type, 'http') => self::REST_PULL,
            str_contains($type, 'sftp') => self::SFTP,
            str_contains($type, 'ftp') => self::FTP,
            default => self::UPLOAD,
        };
    }

    /** Le canal est-il relevé par le runner horaire ? */
    public static function isScheduled(CustomerImportConfiguration $configuration): bool
    {
        return self::of($configuration) !== self::UPLOAD;
    }

    /**
     * L'origine que porteront les commandes (`Order.source`).
     *
     * Un pull REST donne `rest_api` quel que soit le format de la réponse ; un
     * fichier, son format — Excel `excel_import`, XML `xml_import`, etc.
     */
    public static function orderSource(CustomerImportConfiguration $configuration): OrderSource
    {
        if (self::of($configuration) === self::REST_PULL) {
            return OrderSource::REST_API;
        }

        return match (PayloadParsers::kind((string) $configuration->file_format)) {
            'xlsx' => OrderSource::EXCEL_IMPORT,
            'xml' => OrderSource::XML_IMPORT,
            'json' => OrderSource::JSON_IMPORT,
            default => OrderSource::CSV_IMPORT,
        };
    }

    /** Un utilisateur peut-il déposer un fichier pour cette configuration ? */
    public static function acceptsUpload(CustomerImportConfiguration $configuration): bool
    {
        return PayloadParsers::kind((string) $configuration->file_format) !== null;
    }
}
