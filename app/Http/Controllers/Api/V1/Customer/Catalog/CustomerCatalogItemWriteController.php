<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\Catalog\UpdateCustomerCatalogItemRequest;
use App\Http\Resources\Api\V1\Customer\Catalog\CustomerCatalogItemResource;
use App\Modules\Catalogs\Models\CustomerCatalogItem;
use App\Modules\CustomerPortal\Actions\UpdateCustomerCatalogItem;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Modifier, désactiver, réactiver un article du catalogue depuis le portail.
 *
 * Permissions (route) : `customer.catalog.view` + `customer.articles.update`.
 * L'article est résolu par `CustomerCatalogScope` dans l'action — jamais par
 * liaison de modèle : celui d'un autre client répond le même 404 qu'un
 * identifiant inexistant.
 */
class CustomerCatalogItemWriteController extends Controller
{
    public function __construct(private readonly UpdateCustomerCatalogItem $action) {}

    public function update(UpdateCustomerCatalogItemRequest $request, string $item): JsonResponse
    {
        return $this->present($this->action->update($item, $request->validated(), $request));
    }

    public function deactivate(Request $request, string $item): JsonResponse
    {
        return $this->present($this->action->setActive($item, false, $request));
    }

    public function activate(Request $request, string $item): JsonResponse
    {
        return $this->present($this->action->setActive($item, true, $request));
    }

    private function present(CustomerCatalogItem $item): JsonResponse
    {
        return ApiResponse::ok(new CustomerCatalogItemResource($item->load('catalog'), null));
    }
}
