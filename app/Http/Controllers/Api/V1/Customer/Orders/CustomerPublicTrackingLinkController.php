<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer\Orders;

use App\Http\Controllers\Api\V1\Customer\Concerns\ResolvesCustomerOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\Orders\StorePublicTrackingLinkRequest;
use App\Modules\CustomerPortal\Actions\GeneratePublicTrackingLink;
use App\Modules\CustomerPortal\Actions\RevokePublicTrackingLink;
use App\Modules\CustomerPortal\Models\PublicTrackingLink;
use App\Modules\CustomerPortal\Services\CurrentCustomerContext;
use App\Modules\CustomerPortal\Services\Tracking\PublicTrackingLinks;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lien public de suivi d'une commande du Customer courant (§22-§24).
 *
 * Consulter : `customer.tracking.public_link.view`. Générer et révoquer :
 * `customer.tracking.public_link.manage`. La commande est toujours cherchée
 * dans le client du jeton — celle d'un autre client répond 404.
 */
class CustomerPublicTrackingLinkController extends Controller
{
    use ResolvesCustomerOrder;

    public function __construct(
        private readonly CurrentCustomerContext $context,
        private readonly PublicTrackingLinks $links,
    ) {}

    public function show(string $order): JsonResponse
    {
        $resolved = $this->order($this->context, $order);

        return ApiResponse::ok($this->links->present(PublicTrackingLink::where('order_id', $resolved->id)->first()));
    }

    /**
     * Le jeton brut n'est rendu qu'ici, dans l'URL publique : il n'est
     * conservé nulle part ailleurs.
     */
    public function store(StorePublicTrackingLinkRequest $request, string $order, GeneratePublicTrackingLink $generate): JsonResponse
    {
        $result = $generate->execute($this->order($this->context, $order), $request->validated('expiresAt'), $request);

        return ApiResponse::created($this->links->present($result['link'], $result['token']));
    }

    public function destroy(Request $request, string $order, RevokePublicTrackingLink $revoke): JsonResponse
    {
        $link = $revoke->execute($this->order($this->context, $order), $request);

        return ApiResponse::ok($this->links->present($link));
    }
}
