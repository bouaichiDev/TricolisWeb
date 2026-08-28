import { useTranslation } from 'react-i18next'

import { useBillableServices } from '../hooks/useInvoices'
import type { BillableService } from '../types/invoice'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { SearchInput } from '@/shared/components/data/SearchInput'
import { EmptyState } from '@/shared/components/feedback/EmptyState'
import { Checkbox } from '@/shared/components/ui/checkbox'
import { Input } from '@/shared/components/ui/input'
import { Label } from '@/shared/components/ui/label'
import { formatDate, formatMoney } from '@/shared/utils/format'

export interface BillablePeriod {
  from: string
  to: string
}

interface BillableServicePickerProps {
  customerId: string
  currencyCode: string
  period: BillablePeriod
  onPeriodChange: (period: BillablePeriod) => void
  search: string
  onSearchChange: (search: string) => void
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
 * refuserait. La liste affiche ce qu'on lui répond, sans filtrer davantage.
 *
 * La sélection survit à la pagination et aux filtres : elle est portée par la
 * page, pas par la table. Un facturier compose souvent sur deux pages, et
 * perdre son choix en changeant de page l'obligerait à tout reprendre.
 */
export function BillableServicePicker({
  customerId,
  currencyCode,
  period,
  onPeriodChange,
  search,
  onSearchChange,
  page,
  onPageChange,
  selected,
  onToggle,
}: BillableServicePickerProps) {
  const { t } = useTranslation()

  const { data, isPending, error, refetch } = useBillableServices(customerId, {
    page,
    search: search || undefined,
    periodFrom: period.from || undefined,
    periodTo: period.to || undefined,
  })

  const columns: Column<BillableService>[] = [
    {
      key: 'select',
      header: '',
      className: 'w-10',
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
      cell: (row) => (
        <span className="flex flex-col">
          <span>{row.orderNumber}</span>
          <span className="text-xs text-muted-foreground">{row.customerReference ?? ''}</span>
        </span>
      ),
    },
    {
      key: 'requestedDate',
      header: t('billing.invoices.picker.date'),
      cell: (row) => formatDate(row.requestedDate),
    },
    {
      key: 'address',
      header: t('billing.invoices.picker.address'),
      cell: (row) => (row.address ? `${row.address.postalCode ?? ''} ${row.address.city ?? ''}` : ''),
    },
    {
      key: 'quantity',
      header: t('billing.invoices.picker.quantity'),
      className: 'text-right',
      cell: (row) => <span className="tabular-nums">{row.quantity}</span>,
    },
    {
      key: 'customerUnitPrice',
      header: t('billing.invoices.picker.unitPrice'),
      className: 'text-right',
      cell: (row) => (
        <span className="tabular-nums">{formatMoney(row.customerUnitPrice, currencyCode)}</span>
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
    <div className="flex flex-col gap-4">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
        <div className="flex flex-col gap-2">
          <Label htmlFor="billable-from">{t('billing.invoices.picker.periodFrom')}</Label>
          <Input
            id="billable-from"
            type="date"
            value={period.from}
            onChange={(event) => onPeriodChange({ ...period, from: event.target.value })}
            className="w-44"
          />
        </div>
        <div className="flex flex-col gap-2">
          <Label htmlFor="billable-to">{t('billing.invoices.picker.periodTo')}</Label>
          <Input
            id="billable-to"
            type="date"
            value={period.to}
            onChange={(event) => onPeriodChange({ ...period, to: event.target.value })}
            className="w-44"
          />
        </div>
        <SearchInput value={search} onChange={onSearchChange} />
      </div>

      <DataTable
        columns={columns}
        rows={data?.data ?? []}
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
