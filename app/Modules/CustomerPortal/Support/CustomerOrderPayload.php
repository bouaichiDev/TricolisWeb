<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Support;

use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Services\CustomerDefaultAgency;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Models\Order;
use Illuminate\Support\Arr;

/**
 * Traduit une commande saisie dans le portail en l'entrée que le service
 * métier interne (`CreateOrderData`) attend.
 *
 * C'est ici que le portail **impose** ce que le client ne choisit pas :
 *
 * - `customerId` : le client courant, jamais une valeur de la requête ;
 * - `agencyId` : l'agence par défaut du client ;
 * - `source` : `customer_portal` ;
 * - aucune donnée interne (remarque interne, prix, coûts, statut, code-barres)
 *   ne passe : seules les clés listées ci-dessous sont recopiées.
 */
final readonly class CustomerOrderPayload
{
    private const array LINE_KEYS = [
        'catalogItemId', 'name', 'articleCode', 'description', 'quantity',
        'weight', 'volume', 'length', 'width', 'height',
    ];

    private const array PACKAGE_KEYS = [
        'key', 'packageTypeId', 'groupingTypeId', 'reference', 'description',
        'quantity', 'weight', 'volume', 'length', 'width', 'height',
    ];

    public function __construct(
        private CustomerDefaultAgency $agencies,
        private CustomerServicePayload $services,
    ) {}

    /**
     * @param  array<string, mixed>  $input  validé par la FormRequest du portail
     * @return array<string, mixed> entrée de `CreateOrderData::fromArray`
     */
    public function forCreation(array $input, Customer $customer): array
    {
        $packages = $this->packages($input['packages'] ?? []);

        return [
            ...$this->header($input),
            'customerId' => $customer->id,
            'agencyId' => $this->agencies->for($customer),
            'source' => OrderSource::CUSTOMER_PORTAL->value,
            'currencyCode' => $customer->organization?->currency_code,
            'lines' => $this->lines($input['lines'] ?? []),
            'packages' => $packages,
            'services' => $this->services->map($input['services'] ?? [], $packages, $customer),
        ];
    }

    /**
     * Le contenu seul, pour réécrire une commande existante.
     *
     * @param  array<string, mixed>  $input
     * @return array{header: array<string, mixed>, lines: list<array<string, mixed>>, packages: list<array<string, mixed>>, services: list<array<string, mixed>>}
     */
    public function forUpdate(array $input, Order $order): array
    {
        $customer = $order->customer;
        $packages = $this->packages($input['packages'] ?? []);

        return [
            'header' => $this->header($input),
            'lines' => $this->lines($input['lines'] ?? []),
            'packages' => $packages,
            'services' => $this->services->map($input['services'] ?? [], $packages, $customer, $order),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function header(array $input): array
    {
        return array_filter([
            'orderDate' => $input['orderDate'] ?? null,
            'customerReference' => $input['customerReference'] ?? null,
            'externalReference' => $input['externalReference'] ?? null,
            'workerRemark' => $input['workerRemark'] ?? null,
        ], static fn ($value): bool => $value !== null);
    }

    /**
     * Une ligne vient du catalogue du client (`catalogItemId`) ou d'une saisie
     * libre (`name`) : les deux sont admises. Un article désigné doit en
     * revanche être celui d'un catalogue actif **de ce client** — c'est
     * `OrderScopeGuard::catalogItem` qui le vérifie, et refuse sinon.
     *
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    private function lines(array $lines): array
    {
        return array_map(static fn (array $line): array => Arr::only($line, self::LINE_KEYS), array_values($lines));
    }

    /**
     * @param  list<array<string, mixed>>  $packages
     * @return list<array<string, mixed>>
     */
    private function packages(array $packages): array
    {
        return array_map(static fn (array $package): array => [
            ...Arr::only($package, self::PACKAGE_KEYS),
            'lines' => array_map(
                static fn (array $line): array => ['lineKey' => (string) $line['lineKey'], 'quantity' => $line['quantity']],
                $package['lines'] ?? [],
            ),
        ], array_values($packages));
    }
}
