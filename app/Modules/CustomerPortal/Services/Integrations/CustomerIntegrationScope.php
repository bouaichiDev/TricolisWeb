<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Services\Integrations;

use App\Modules\CustomerPortal\Services\CurrentCustomerContext;
use App\Modules\Exports\Models\CustomerExportConfiguration;
use App\Modules\Exports\Models\ExportJob;
use App\Modules\Integrations\Models\CustomerApiConfiguration;
use App\Modules\Integrations\Models\CustomerImportConfiguration;
use App\Modules\Integrations\Models\CustomerImportRun;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

/**
 * Le périmètre des intégrations dans le portail : **le client courant**, dans
 * **son organisation**. Toute lecture du module part d'ici.
 *
 * Aucun identifiant de client n'est lu dans la requête : une configuration ou
 * un envoi d'un autre client n'est pas « interdit », il **n'existe pas** (404)
 * — le portail ne confirme pas l'existence de ce qui ne le regarde pas.
 */
final readonly class CustomerIntegrationScope
{
    public function __construct(private CurrentCustomerContext $context) {}

    /** @return Builder<CustomerImportConfiguration> */
    public function importConfigurations(): Builder
    {
        return CustomerImportConfiguration::query()
            ->where('customer_id', $this->context->customerId())
            ->whereHas('customer', fn (Builder $customer) => $customer->where('organization_id', $this->context->organizationId()));
    }

    /** @return Builder<CustomerExportConfiguration> */
    public function exportConfigurations(): Builder
    {
        return CustomerExportConfiguration::query()
            ->where('customer_id', $this->context->customerId())
            ->whereHas('customer', fn (Builder $customer) => $customer->where('organization_id', $this->context->organizationId()));
    }

    /** @return Builder<CustomerApiConfiguration> */
    public function apiConfigurations(): Builder
    {
        return CustomerApiConfiguration::query()
            ->where('customer_id', $this->context->customerId())
            ->whereHas('customer', fn (Builder $customer) => $customer->where('organization_id', $this->context->organizationId()));
    }

    /**
     * Les envois du client, et seulement ceux dont la configuration est la
     * sienne : les trois clients (job, configuration, organisation) concordent.
     *
     * @return Builder<ExportJob>
     */
    public function exportJobs(): Builder
    {
        return ExportJob::query()
            ->where('customer_id', $this->context->customerId())
            ->whereIn('configuration_id', $this->exportConfigurations()->select('id'));
    }

    /**
     * L'historique d'import du client, limité à ses propres configurations.
     *
     * @return Builder<CustomerImportRun>
     */
    public function importRuns(): Builder
    {
        return CustomerImportRun::query()
            ->where('customer_id', $this->context->customerId())
            ->whereIn('configuration_id', $this->importConfigurations()->select('id'));
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return TModel
     */
    public function find(Builder $query, string $id): Model
    {
        if (! Str::isUlid($id)) {
            throw (new ModelNotFoundException)->setModel($query->getModel()::class);
        }

        return $query->whereKey($id)->firstOrFail();
    }
}
