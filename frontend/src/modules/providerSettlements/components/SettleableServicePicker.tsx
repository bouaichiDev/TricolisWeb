import { useTranslation } from 'react-i18next'

import { useSettleableServices } from '../hooks/useSettlements'
import type { SettleableService } from '../types/settlement'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { SearchInput } from '@/shared/components/data/SearchInput'
import { EmptyState } from '@/shared/components/feedback/EmptyState'
import { Checkbox } from '@/shared/components/ui/checkbox'
import { Input } from '@/shared/components/ui/input'
import { Label } from '@/shared/components/ui/label'
import { formatDate, formatMoney } from '@/shared/utils/format'

export interface SettleablePeriod {
  from: string
  to: string
}

interface SettleableServicePickerProps {
  providerId: string
  period: SettleablePeriod
  onPeriodChange: (period: SettleablePeriod) => void
  search: string
  onSearchChange: (search: string) => void
  page: number
  onPageChange: (page: number) => void
  selected: Map<string, SettleableService>
  onToggle: (service: SettleableService) => void
}

/**
 * Les prestations qu'il reste à régler à ce fournisseur.
 *
 * **Ce sont celles de son affectation active.** Le §17 le veut : un service
 * peut être passé chez plusieurs fournisseurs — une tentative échouée chez
 * l'un, la livraison chez l'autre — et le serveur ne propose ici que celles
 * que ce fournisseur a réellement exécutées.
 *
 * Le coût affiché est celui de la commande, pas le prix client : ce dernier est
 * montré à côté pour se repérer, jamais comme base de paiement.
 */
export function SettleableServicePicker({
  providerId,
  period,
  onPeriodChange,
  search,
  onSearchChange,
  page,
  onPageChange,
  selected,
  onToggle,
}: SettleableServicePickerProps) {
  const { t } = useTranslation()

  const { data, isPending, error, refetch } = useSettleableServices(providerId, {
    page,
    search: search || undefined,
    periodFrom: period.from || undefined,
    periodTo: period.to || undefined,
  })

  const columns: Column<SettleableService>[] = [
    {
      key: 'select',
      header: '',
      className: 'w-10',
      cell: (row) => (
        <Checkbox
          checked={selected.has(row.id)}
          onCheckedChange={() => onToggle(row)}
          aria-label={t('settlements.picker.select', { number: row.serviceNumber })}
        />
      ),
    },
    {
      key: 'serviceNumber',
      header: t('settlements.picker.service'),
      cell: (row) => (
        <span className="flex flex-col">
          <span className="font-medium">{row.serviceNumber}</span>
          <span className="text-xs text-muted-foreground">{row.serviceName ?? row.serviceCode}</span>
        </span>
      ),
    },
    {
      key: 'customerName',
      header: t('settlements.picker.customer'),
      cell: (row) => (
        <span className="flex flex-col">
          <span>{row.customerName ?? ''}</span>
          <span className="text-xs text-muted-foreground">{row.orderNumber}</span>
        </span>
      ),
    },
    {
      key: 'requestedDate',
      header: t('settlements.picker.date'),
      cell: (row) => formatDate(row.requestedDate),
    },
    {
      key: 'quantity',
      header: t('settlements.picker.quantity'),
      className: 'text-right',
      cell: (row) => <span className="tabular-nums">{row.quantity}</span>,
    },
    {
      key: 'providerUnitCost',
      header: t('settlements.picker.unitCost'),
      className: 'text-right',
      cell: (row) => <span className="tabular-nums">{formatMoney(row.providerUnitCost)}</span>,
    },
    {
      key: 'customerUnitPrice',
      header: t('settlements.picker.customerPrice'),
      className: 'text-right',
      cell: (row) => (
        <span className="tabular-nums text-muted-foreground">
          {formatMoney(row.customerUnitPrice)}
        </span>
      ),
    },
  ]

  if (providerId === '') {
    return (
      <EmptyState
        title={t('settlements.picker.chooseProviderTitle')}
        description={t('settlements.picker.chooseProvider')}
      />
    )
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
        <div className="flex flex-col gap-2">
          <Label htmlFor="settleable-from">{t('settlements.picker.periodFrom')}</Label>
          <Input
            id="settleable-from"
            type="date"
            value={period.from}
            onChange={(event) => onPeriodChange({ ...period, from: event.target.value })}
            className="w-44"
          />
        </div>
        <div className="flex flex-col gap-2">
          <Label htmlFor="settleable-to">{t('settlements.picker.periodTo')}</Label>
          <Input
            id="settleable-to"
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
        emptyMessage={t('settlements.picker.empty')}
      />
    </div>
  )
}
