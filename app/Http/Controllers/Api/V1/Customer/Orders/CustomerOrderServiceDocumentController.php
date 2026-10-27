<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer\Orders;

use App\Http\Controllers\Api\V1\Customer\Concerns\ResolvesCustomerOrderService;
use App\Http\Controllers\Controller;
use App\Modules\CustomerPortal\Services\CurrentCustomerContext;
use App\Modules\CustomerPortal\Services\Documents\CustomerDocumentAccess;
use App\Modules\CustomerPortal\Services\Documents\CustomerDocumentFiles;
use App\Modules\Documents\Models\Document;
use App\Shared\Database\MorphMap;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Documents d'une prestation — dont son bon de livraison (§15-§16).
 *
 * Le BL généré par l'interne est lié à la commande **et** au service
 * (`GenerateDeliveryNoteAction`) : il apparaît ici, marqué `isDeliveryNote`.
 * Aucun BL n'est produit par le portail. Permission : `customer.orders.documents.view`.
 */
class CustomerOrderServiceDocumentController extends Controller
{
    use ResolvesCustomerOrderService;

    public function __invoke(string $order, string $service, CurrentCustomerContext $context, CustomerDocumentAccess $access): JsonResponse
    {
        [, $resolved] = $this->orderService($context, $order, $service);

        $documents = $access->linkedTo(MorphMap::ORDER_SERVICE, $resolved->id)->latest()->get();

        return ApiResponse::ok($documents->map(static fn (Document $document): array => CustomerDocumentFiles::present($document))->all());
    }
}
