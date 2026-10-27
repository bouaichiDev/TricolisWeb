<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer\Documents;

use App\Http\Controllers\Controller;
use App\Modules\CustomerPortal\Services\Documents\CustomerDocumentAccess;
use App\Modules\CustomerPortal\Services\Documents\CustomerDocumentFiles;
use App\Modules\Documents\Models\Document;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Un document du portail, par son identifiant (§15).
 *
 * Aucune permission unique sur la route : chaque chemin d'accès porte la
 * sienne (`CustomerDocumentAccess`). Un bon de livraison se lit avec les
 * documents de commande, une signature avec les preuves de livraison, une
 * pièce jointe avec les réclamations. Hors chemin : 404.
 */
class CustomerDocumentController extends Controller
{
    public function __construct(private readonly CustomerDocumentAccess $access) {}

    public function show(string $document): JsonResponse
    {
        return ApiResponse::ok(CustomerDocumentFiles::present($this->find($document)));
    }

    public function download(string $document): StreamedResponse
    {
        return CustomerDocumentFiles::download($this->find($document));
    }

    private function find(string $id): Document
    {
        if (! Str::isUlid($id)) {
            throw (new ModelNotFoundException)->setModel(Document::class);
        }

        return $this->access->query()->findOrFail($id);
    }
}
