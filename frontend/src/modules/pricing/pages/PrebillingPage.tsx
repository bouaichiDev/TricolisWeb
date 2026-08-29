import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import { usePrebilling } from '../hooks/usePricing'
import type { PrebillingService } from '../types/pricing'
import { useCustomerList } from '@/modules/customers/hooks/useCustomers'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { SearchInput } from '@/shared/components/data/SearchInput'
import { AsyncSelect } from '@/shared/components/form/AsyncSelect'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { Badge } from '@/shared/components/ui/badge'
import { Input } from '@/shared/components/ui/input'
import { Label } from '@/shared/components/ui/label'
import { formatDate, formatMoney } from '@/shared/utils/format'

/** Sentinelle « tous les clients » : Radix refuse une option de valeur vide. */
const ALL_CUSTOMERS = 'all'

/**
 * Ce qui reste à facturer, et ce que le barème donnerait.
 *
 * **La page qui sert à trouver les trous.** Une prestation terminée qu'aucun
 * barème ne couvre se facturerait à zéro ou pas du tout, et l'on ne s'en
 * apercevrait qu'en éditant la facture — devant le client. Ici, elle se voit
 * avant, avec la raison.
 *
 * Le prix affiché est **calculé sans être enregistré** : rien n'est figé tant
 * qu'une facture ne l'a pas repris.
 */
export function PrebillingPage() {
  const { t } = useTranslation()
  const [customerId, setCustomerId] = useState(ALL_CUSTOMERS)
  const [periodFrom, setPeriodFrom] = useState('')
  const [periodTo, setPeriodTo] = useState('')
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)

  const customers = useCustomerList({ page: 1, perPage: 100 })

  const { data, isPending, error, refetch } = usePrebilling({
    page,
    search: search || undefined,
    customerId: customerId === ALL_CUSTOMERS ? undefined : customerId,
    periodFrom: periodFrom || undefined,
    periodTo: periodTo || undefined,
  })

  const rows = data?.data ?? []
  const missing = rows.filter((row) => !row.priced).length

  const columns: Column<PrebillingService>[] = [
    {
      key: 'serviceNumber',
      header: t('pricing.prebilling.fields.service'),
      cell: (row) => (
        <span className="flex flex-col">
          <Link to={`/orders/${row.orderId}`} className="font-medium text-primary hover:underline">
            {row.serviceNumber}
          </Link>
          <span className="text-xs text-muted-foreground">
            {row.serviceName ?? row.serviceCode}
          </span>
        </span>
      ),
    },
    {
      key: 'customer',
      header: t('pricing.prebilling.fields.customer'),
      cell: (row) => (
        <span className="flex flex-col">
          <span>{row.customerName}</span>
          <span className="text-xs text-muted-foreground">{row.orderNumber}</span>
        </span>
      ),
    },
    {
      key: 'requestedDate',
      header: t('pricing.prebilling.fields.date'),
      cell: (row) => formatDate(row.requestedDate),
    },
    {
      key: 'measures',
      header: t('pricing.prebilling.fields.measures'),
      cell: (row) => (
        <span className="flex flex-col text-xs">
          <span>{t('pricing.prebilling.weight', { value: row.weight })}</span>
          <span>{t('pricing.prebilling.volume', { value: row.volume })}</span>
          <span className="text-muted-foreground">
            {[row.postalCode, row.city].filter(Boolean).join(' ')}
          </span>
        </span>
      ),
    },
    {
      key: 'source',
      header: t('pricing.prebilling.fields.source'),
      cell: (row) =>
        row.priced ? (
          <span className="flex flex-col gap-1">
            <Badge variant={row.scope === 'customer' ? 'default' : 'outline'}>
              {t(`pricing.scopes.${row.scope}`)}
            </Badge>
            <code className="text-xs">{row.formula}</code>
            {row.zone ? (
              <span className="text-xs text-muted-foreground">{row.zone}</span>
            ) : null}
          </span>
        ) : (
          <span className="text-sm text-destructive">{row.reason}</span>
        ),
    },
    {
      key: 'calculatedPrice',
      header: t('pricing.prebilling.fields.price'),
      className: 'text-right',
      cell: (row) =>
        row.priced ? (
          <span className="tabular-nums font-medium">
            {formatMoney(row.calculatedPrice ?? '0', row.currencyCode)}
          </span>
        ) : (
          <Badge variant="destructive">{t('pricing.prebilling.notConfigured')}</Badge>
        ),
    },
  ]

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={t('pricing.prebilling.title')}
        description={t('pricing.prebilling.subtitle')}
      />

      <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
        <div className="w-full sm:w-56">
          <AsyncSelect
            label={t('pricing.prebilling.fields.customer')}
            value={customerId}
            onChange={(value) => {
              setCustomerId(value)
              setPage(1)
            }}
            options={[
              { value: ALL_CUSTOMERS, label: t('pricing.prebilling.allCustomers') },
              ...(customers.data?.data ?? []).map((customer) => ({
                value: customer.id,
                label: customer.name,
                hint: customer.code,
              })),
            ]}
            isLoading={customers.isPending}
          />
        </div>

        <div className="flex flex-col gap-2">
          <Label htmlFor="prebilling-from">{t('pricing.prebilling.periodFrom')}</Label>
          <Input
            id="prebilling-from"
            type="date"
            value={periodFrom}
            onChange={(event) => {
              setPeriodFrom(event.target.value)
              setPage(1)
            }}
            className="w-44"
          />
        </div>

        <div className="flex flex-col gap-2">
          <Label htmlFor="prebilling-to">{t('pricing.prebilling.periodTo')}</Label>
          <Input
            id="prebilling-to"
            type="date"
            value={periodTo}
            onChange={(event) => {
              setPeriodTo(event.target.value)
              setPage(1)
            }}
            className="w-44"
          />
        </div>

        <SearchInput
          value={search}
          onChange={(value) => {
            setSearch(value)
            setPage(1)
          }}
        />
      </div>

      {missing > 0 ? (
        <p className="rounded-md border border-destructive/40 bg-destructive/5 px-3 py-2 text-sm">
          {t('pricing.prebilling.missingCount', { count: missing })}
        </p>
      ) : null}

      <DataTable
        columns={columns}
        rows={rows}
        rowKey={(row) => row.id}
        meta={data?.meta}
        isLoading={isPending}
        error={error}
        onPageChange={setPage}
        onRetry={() => void refetch()}
        emptyMessage={t('pricing.prebilling.empty')}
      />
    </div>
  )
}
