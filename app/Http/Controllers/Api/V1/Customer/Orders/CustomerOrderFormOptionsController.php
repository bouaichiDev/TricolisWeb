<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer\Orders;

use App\Http\Controllers\Controller;
use App\Modules\Catalogs\Models\CustomerCatalogItem;
use App\Modules\CustomerPortal\Services\CurrentCustomerContext;
use App\Modules\CustomerPortal\Services\CustomerAddressBook;
use App\Modules\Orders\Models\Service;
use App\Modules\Statuses\Services\StatusMachine;
use App\Modules\Types\Models\TypeItem;
use App\Shared\Database\MorphMap;
use App\Shared\Enums\ContactRole;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Référentiels du formulaire de commande, lus en base et bornés au client :
 * services et types de colis du transporteur, carnet d'adresses et
 * catalogue **du client**. Rien n'est écrit en dur dans le portail.
 */
class CustomerOrderFormOptionsController extends Controller
{
    public function __construct(private readonly CurrentCustomerContext $context) {}

    public function options(CustomerAddressBook $addresses, StatusMachine $statuses): JsonResponse
    {
        $customer = $this->context->customer();
        $organizationId = $customer->organization_id;

        return ApiResponse::ok([
            'catalogEnabled' => (bool) $customer->catalog_enabled,
            'services' => Service::where('organization_id', $organizationId)->where('status', 'active')
                ->orderBy('name')->get(['id', 'code', 'name', 'unit', 'requires_address', 'requires_contact'])
                ->map(static fn (Service $service): array => [
                    'id' => $service->id,
                    'code' => $service->code,
                    'name' => $service->name,
                    'requiresContact' => (bool) $service->requires_contact,
                ])->values(),
            'packageTypes' => $this->typeItems($organizationId, 'package'),
            'groupingTypes' => $this->typeItems($organizationId, 'grouping'),
            'addresses' => $addresses->query($customer)->orderBy('name')->limit(500)->get()->map(static fn ($address): array => [
                'id' => $address->id,
                'name' => $address->name,
                'addressLine1' => $address->address_line_1,
                'postalCode' => $address->postal_code,
                'city' => $address->city,
                'country' => $address->country,
            ])->values(),
            'contactRoles' => array_map(static fn (ContactRole $role): array => ['value' => $role->value, 'label' => $role->label()], ContactRole::cases()),
            'statuses' => $statuses->statuses(MorphMap::ORDER)->where('active', true)
                ->map(static fn ($status): array => ['code' => $status->code, 'label' => $status->label])->values(),
        ]);
    }

    /**
     * Articles du catalogue du client, par recherche. Seuls les articles
     * actifs de catalogues actifs de **ce** client sortent.
     */
    public function catalogItems(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));

        $items = CustomerCatalogItem::query()
            ->where('status', 'active')
            ->whereHas('catalog', fn ($query) => $query->where('customer_id', $this->context->customerId())->where('status', 'active'))
            ->when($search !== '', static fn ($query) => $query->where(static fn ($builder) => $builder
                ->where('article_code', 'like', "%$search%")
                ->orWhere('barcode', 'like', "%$search%")
                ->orWhere('name', 'like', "%$search%")))
            ->orderBy('name')
            ->limit(50)
            ->get();

        return ApiResponse::ok($items->map(static fn (CustomerCatalogItem $item): array => [
            'id' => $item->id,
            'articleCode' => $item->article_code,
            'name' => $item->name,
            'description' => $item->description,
            'weight' => $item->weight,
            'volume' => $item->volume,
        ])->values());
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    private function typeItems(string $organizationId, string $type): array
    {
        return TypeItem::where('organization_id', $organizationId)
            ->where('status', 'active')
            ->whereHas('type', static fn ($query) => $query->where('code', $type))
            ->orderBy('position')
            ->get(['id', 'name'])
            ->map(static fn (TypeItem $item): array => ['id' => $item->id, 'name' => $item->name])
            ->values()
            ->all();
    }
}
