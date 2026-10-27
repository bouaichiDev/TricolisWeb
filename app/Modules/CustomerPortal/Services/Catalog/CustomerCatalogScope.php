<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Services\Catalog;

use App\Modules\Catalogs\Models\CustomerCatalog;
use App\Modules\Catalogs\Models\CustomerCatalogItem;
use App\Modules\CustomerPortal\Services\CurrentCustomerContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

/**
 * Le périmètre du catalogue dans le portail : les catalogues **du client
 * courant**, dans **son organisation**, et leurs articles.
 *
 * Un article est atteint par son catalogue (`Customer → CustomerCatalog →
 * CustomerCatalogItem`) : celui d'un autre client n'existe pas (404).
 */
final readonly class CustomerCatalogScope
{
    public function __construct(private CurrentCustomerContext $context) {}

    /** @return Builder<CustomerCatalog> */
    public function catalogs(): Builder
    {
        return CustomerCatalog::query()
            ->where('customer_id', $this->context->customerId())
            ->whereHas('customer', fn (Builder $customer) => $customer->where('organization_id', $this->context->organizationId()));
    }

    /** @return Builder<CustomerCatalogItem> */
    public function items(): Builder
    {
        return CustomerCatalogItem::query()->whereIn('catalog_id', $this->catalogs()->select('id'));
    }

    public function item(string $id): CustomerCatalogItem
    {
        return $this->find($this->items()->with('catalog'), $id, CustomerCatalogItem::class);
    }

    /**
     * L'article du client, relu **sous verrou** pour l'écrire. Même portée que
     * `item()` — jamais l'identifiant seul : l'article d'un autre client reste
     * introuvable, même au moment de l'écriture.
     */
    public function lockedItem(string $id): CustomerCatalogItem
    {
        return $this->find($this->items()->lockForUpdate(), $id, CustomerCatalogItem::class);
    }

    /**
     * Un catalogue actif du client, pour y ajouter un article.
     */
    public function activeCatalog(string $id): CustomerCatalog
    {
        return $this->find($this->catalogs()->where('status', 'active'), $id, CustomerCatalog::class);
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  class-string<TModel>  $model
     * @return TModel
     */
    private function find(Builder $query, string $id, string $model): mixed
    {
        if (! Str::isUlid($id)) {
            throw (new ModelNotFoundException)->setModel($model);
        }

        return $query->whereKey($id)->firstOrFail();
    }
}
