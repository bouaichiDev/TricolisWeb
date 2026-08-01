<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Orders;

use App\Modules\Orders\Enums\OrderServiceStatus;
use App\Modules\Orders\Enums\OrderSource;
use App\Shared\Enums\ContactRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Payload de création complète d'une commande.
 *
 * Le diagramme impose `Order 1 *-- 1..* OrderLine` et
 * `Order 1 *-- 1..* OrderService` : une commande sans ligne ou sans service
 * n'existe pas. Les colis sont facultatifs.
 *
 * Le statut initial n'est pas acceptable en entrée : toute commande naît en
 * brouillon et évolue par l'endpoint de transition.
 */
class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge($this->headerRules(), $this->lineRules(), $this->packageRules(), $this->serviceRules());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function headerRules(): array
    {
        return [
            'customerId' => ['required', 'ulid'],
            'agencyId' => ['required', 'ulid'],
            'depotId' => ['nullable', 'ulid'],
            'externalReference' => ['nullable', 'string', 'max:255'],
            'customerReference' => ['nullable', 'string', 'max:255'],
            'orderType' => ['nullable', 'string', 'max:64'],
            'groupCode' => ['nullable', 'string', 'max:255'],
            'orderDate' => ['required', 'date'],
            'source' => ['sometimes', Rule::enum(OrderSource::class)],
            'currencyCode' => ['sometimes', 'string', 'size:3', 'uppercase'],
            'internalRemark' => ['nullable', 'string'],
            'workerRemark' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function lineRules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.catalogItemId' => ['nullable', 'ulid'],
            'lines.*.name' => ['required_without:lines.*.catalogItemId', 'nullable', 'string', 'max:255'],
            'lines.*.articleCode' => ['nullable', 'string', 'max:255'],
            'lines.*.barcode' => ['nullable', 'string', 'max:255'],
            'lines.*.externalReference' => ['nullable', 'string', 'max:255'],
            'lines.*.description' => ['nullable', 'string'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.weight' => ['sometimes', 'numeric', 'min:0'],
            'lines.*.volume' => ['sometimes', 'numeric', 'min:0'],
            'lines.*.length' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'lines.*.width' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'lines.*.height' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'lines.*.purchasePrice' => ['nullable', 'numeric', 'min:0'],
            'lines.*.sellingPrice' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function packageRules(): array
    {
        return [
            'packages' => ['sometimes', 'array'],
            'packages.*.key' => ['nullable', 'string', 'max:64'],
            'packages.*.parentKey' => ['nullable', 'string', 'max:64'],
            'packages.*.packageTypeId' => ['nullable', 'ulid'],
            'packages.*.groupingTypeId' => ['nullable', 'ulid'],
            'packages.*.barcode' => ['nullable', 'string', 'max:128'],
            'packages.*.reference' => ['nullable', 'string', 'max:255'],
            'packages.*.description' => ['nullable', 'string', 'max:255'],
            'packages.*.quantity' => ['sometimes', 'numeric', 'gt:0'],
            'packages.*.weight' => ['sometimes', 'numeric', 'min:0'],
            'packages.*.volume' => ['sometimes', 'numeric', 'min:0'],
            'packages.*.lines' => ['sometimes', 'array'],
            'packages.*.lines.*.lineKey' => ['required', 'string', 'max:64'],
            'packages.*.lines.*.quantity' => ['required', 'numeric', 'gt:0'],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function serviceRules(): array
    {
        return [
            'services' => ['required', 'array', 'min:1'],
            'services.*.serviceId' => ['required', 'ulid'],
            'services.*.addressId' => ['required', 'ulid'],
            'services.*.serviceNumber' => ['required', 'string', 'max:255'],
            'services.*.sequence' => ['required', 'integer', 'min:1'],
            'services.*.requestedDate' => ['required', 'date'],
            'services.*.requestedFrom' => ['nullable', 'date'],
            'services.*.requestedTo' => ['nullable', 'date', 'after_or_equal:services.*.requestedFrom'],
            'services.*.quantity' => ['required', 'numeric', 'gt:0'],
            'services.*.unit' => ['required', 'string', 'max:32'],
            'services.*.requiredTimeMinutes' => ['required', 'integer', 'min:0'],
            'services.*.remainingTimeMinutes' => ['required', 'integer', 'min:0'],
            'services.*.weight' => ['required', 'numeric', 'min:0'],
            'services.*.volume' => ['required', 'numeric', 'min:0'],
            'services.*.packageCount' => ['required', 'integer', 'min:0'],
            'services.*.customerUnitPrice' => ['required', 'numeric', 'min:0'],
            'services.*.customerTotalPrice' => ['required', 'numeric', 'min:0'],
            'services.*.providerUnitCost' => ['required', 'numeric', 'min:0'],
            'services.*.providerTotalCost' => ['required', 'numeric', 'min:0'],
            'services.*.instructions' => ['nullable', 'string'],
            'services.*.status' => ['required', Rule::enum(OrderServiceStatus::class)],
            'services.*.contacts' => ['sometimes', 'array'],
            'services.*.contacts.*.contactId' => ['nullable', 'ulid'],
            'services.*.contacts.*.contactRole' => ['sometimes', Rule::enum(ContactRole::class)],
            'services.*.contacts.*.isPrimary' => ['sometimes', 'boolean'],
            'services.*.contacts.*.firstName' => ['required_without:services.*.contacts.*.contactId', 'nullable', 'string', 'max:255'],
            'services.*.contacts.*.lastName' => ['nullable', 'string', 'max:255'],
            'services.*.contacts.*.phone' => ['nullable', 'string', 'max:255'],
            'services.*.contacts.*.mobile' => ['nullable', 'string', 'max:255'],
            'services.*.contacts.*.email' => ['nullable', 'email', 'max:255'],
            'services.*.packages' => ['sometimes', 'array'],
            'services.*.packages.*.packageKey' => ['required', 'string', 'max:64'],
            'services.*.packages.*.quantity' => ['sometimes', 'numeric', 'gt:0'],
            'services.*.packages.*.handlingInstructions' => ['nullable', 'string'],
        ];
    }
}
