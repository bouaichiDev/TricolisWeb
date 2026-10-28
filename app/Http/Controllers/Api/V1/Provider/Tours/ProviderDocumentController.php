<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Provider\Tours;

use App\Http\Controllers\Controller;
use App\Modules\CustomerPortal\Services\Documents\CustomerDocumentFiles;
use App\Modules\ProviderPortal\Services\ProviderDocumentAccess;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Téléchargement d'un document d'exécution : le contrôle d'accès d'abord
 * (ProviderDocumentAccess, 404 hors chemin), puis le flux privé générique,
 * qui ne livre jamais le chemin de stockage et pose `nosniff`.
 */
class ProviderDocumentController extends Controller
{
    public function __invoke(string $document, ProviderDocumentAccess $documents): StreamedResponse
    {
        return CustomerDocumentFiles::download($documents->find($document));
    }
}
