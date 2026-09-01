<?php

declare(strict_types=1);

namespace App\Modules\Orders\Enums;

enum OrderSource: string
{
    case INTERNAL = 'internal';
    case CUSTOMER_PORTAL = 'customer_portal';
    case REST_API = 'rest_api';
    case CSV_IMPORT = 'csv_import';
    case EXCEL_IMPORT = 'excel_import';
    case XML_IMPORT = 'xml_import';
    // Ajoute avec l'import de fichiers : les trois cas d'import couvraient le
    // CSV, l'Excel et le XML, mais pas le JSON — que le lecteur accepte. Sans
    // lui, une commande venue d'un JSON serait marquee `csv_import`, ce qui
    // serait faux.
    case JSON_IMPORT = 'json_import';
    case STOCK = 'stock';
    case CATALOG = 'catalog';
}
