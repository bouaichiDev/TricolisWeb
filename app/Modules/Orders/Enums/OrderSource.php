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
    case STOCK = 'stock';
    case CATALOG = 'catalog';
}
