import { useTranslation } from 'react-i18next'

import { NumberFilter, PeriodFilter, RangeFilter, TextFilter } from './BillableFilterFields'
import {
  hasBillableFilter,
  toBillableQuery,
  type BillableColumnFilters,
} from './billableFilters'
import { useBillableServices } from '../hooks/useInvoices'
import type { BillableService } from '../types/invoice'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { EmptyState } from '@/shared/components/feedback/EmptyState'
import { Button } from '@/shared/components/ui/button'
import { Checkbox } from '@/shared/components/ui/checkbox'
import { formatDate, formatMoney } from '@/shared/utils/format'

interface BillableServicePickerProps {
  customerId: string
  currencyCode: string
  filters: BillableColumnFilters
  onFiltersChange: (patch: Partial<BillableColumnFilters>) => void
  onFiltersReset: () => void
  page: number
  onPageChange: (page: number) => void
  selected: Map<string, BillableService>
  onToggle: (service: BillableService) => void
}

/**
 * Les prestations qu'on peut encore facturer, et celles qu'on retient.
 *
 * **L'éligibilité vient du serveur.** Le §42 l'exige : rejouer la règle ici la
 * ferait diverger, et l'écran proposerait des prestations que la création
 * refuserait. Les filtres de colonne partent au serveur pour la même raison —
 * la liste est paginée, et filtrer les vingt-cinq lignes affichées cacherait
 * tout ce qui suit.
 *
 * La sélection survit à la pagination et aux filtres : elle est portée par la
 * page, pas par la table. Un facturier compose souvent sur deux pages, et
 * perdre son choix en changeant de filtre l'obligerait à tout reprendre.
 */
export function BillableServicePicker({
  customerId,
  currencyCode,
  filters,
  onFiltersChange,
  onFiltersReset,
  page,
  onPageChange,
  selected,
  onToggle,
}: BillableServicePickerProps) {
  const { t } = useTranslation()

  const { data, isPending, error, refetch } = useBillableServices(customerId, {
    page,
    ...toBillableQuery(filters),
  })

  const rows = data?.data ?? []
  const allChosen = rows.length > 0 && rows.every((row) => selected.has(row.id))

  /** Coche ou décoche la page entière, sans toucher aux autres pages. */
  const togglePage = () => {
    for (const row of rows) {
      if (allChosen === selected.has(row.id)) onToggle(row)
    }
  }

  const columns: Column<BillableService>[] = [
    {
      key: 'select',
      header: '',
      className: 'w-10',
      filter: (
        <Checkbox
          checked={allChosen}
          disabled={rows.length === 0}
          onCheckedChange={togglePage}
          aria-label={t('billing.invoices.picker.selectPage')}
        />
      ),
      cell: (row) => (
        <Checkbox
          checked={selected.has(row.id)}
          onCheckedChange={() => onToggle(row)}
          aria-label={t('billing.invoices.picker.select', { number: row.serviceNumber })}
        />
      ),
    },
    {
      key: 'serviceNumber',
      header: t('billing.invoices.picker.service'),
      filter: (
        <NumberFilter
          customerId={customerId}
          field="service"
          label={t('billing.invoices.picker.filterService')}
          value={filters}
          onChange={onFiltersChange}
        />
      ),
      cell: (row) => (
        <span className="flex flex-col">
          <span className="font-medium">{row.serviceNumber}</span>
          <span className="text-xs text-muted-foreground">{row.serviceName ?? row.serviceCode}</span>
        </span>
      ),
    },
    {
      key: 'orderNumber',
      header: t('billing.invoices.picker.order'),
      filter: (
        <NumberFilter
          customerId={customerId}
          field="order"
          label={t('billing.invoices.picker.filterOrder')}
          value={filters}
          onChange={onFiltersChange}
        />
      ),
      cell: (row) => row.orderNumber,
    },
    {
      // La reference du client a sa propre colonne : glissee sous le numero de
      // commande, elle n'avait pas de filtre a elle et se cherchait par
      // accident.
      key: 'customerReference',
      header: t('billing.invoices.picker.reference'),
      filter: (
        <TextFilter
          field="reference"
          label={t('billing.invoices.picker.filterReference')}
          value={filters}
          onChange={onFiltersChange}
        />
      ),
      cell: (row) => row.customerReference ?? '',
    },
    {
      key: 'requestedDate',
      header: t('billing.invoices.picker.date'),
      filter: <PeriodFilter value={filters} onChange={onFiltersChange} />,
      cell: (row) => formatDate(row.requestedDate),
    },
    {
      key: 'address',
      header: t('billing.invoices.picker.address'),
      filter: (
        <TextFilter
          field="address"
          label={t('billing.invoices.picker.filterAddress')}
          value={filters}
          onChange={onFiltersChange}
        />
      ),
      cell: (row) => (row.address ? `${row.address.postalCode ?? ''} ${row.address.city ?? ''}` : ''),
    },
    {
      key: 'quantity',
      header: t('billing.invoices.picker.quantity'),
      className: 'text-right',
      filter: (
        <RangeFilter
          value={filters}
          onChange={onFiltersChange}
          min="quantityMin"
          max="quantityMax"
          labels={{
            min: t('billing.invoices.picker.filterQuantityMin'),
            max: t('billing.invoices.picker.filterQuantityMax'),
          }}
        />
      ),
      cell: (row) => <span className="tabular-nums">{row.quantity}</span>,
    },
    {
      key: 'customerUnitPrice',
      header: t('billing.invoices.picker.unitPrice'),
      className: 'text-right',
      filter: (
        <RangeFilter
          value={filters}
          onChange={onFiltersChange}
          min="priceMin"
          max="priceMax"
          labels={{
            min: t('billing.invoices.picker.filterPriceMin'),
            max: t('billing.invoices.picker.filterPriceMax'),
          }}
        />
      ),
      // La devise est celle de la commande : l'afficher dans celle de la
      // facture avant tout choix montrerait un montant qui n'existe pas.
      cell: (row) => (
        <span className="tabular-nums">
          {formatMoney(row.customerUnitPrice, row.currencyCode ?? currencyCode)}
        </span>
      ),
    },
  ]

  if (customerId === '') {
    return (
      <EmptyState
        title={t('billing.invoices.picker.chooseCustomerTitle')}
        description={t('billing.invoices.picker.chooseCustomer')}
      />
    )
  }

  return (
    <div className="flex flex-col gap-3">
      {hasBillableFilter(filters) ? (
        <div className="flex justify-end">
          <Button variant="ghost" size="sm" onClick={onFiltersReset}>
            {t('billing.invoices.picker.clearFilters')}
          </Button>
        </div>
      ) : null}

      <DataTable
        columns={columns}
        rows={rows}
        rowKey={(row) => row.id}
        meta={data?.meta}
        isLoading={isPending}
        error={error}
        onPageChange={onPageChange}
        onRetry={() => void refetch()}
        emptyMessage={t('billing.invoices.picker.empty')}
      />
    </div>
  )
}
