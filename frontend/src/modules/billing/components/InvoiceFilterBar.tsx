import { useTranslation } from 'react-i18next'

import { useCustomerList } from '@/modules/customers/hooks/useCustomers'
import { StatusFilterSelect } from '@/modules/statuses/components/StatusFilterSelect'
import { SearchInput } from '@/shared/components/data/SearchInput'
import { AsyncSelect } from '@/shared/components/form/AsyncSelect'
import { Input } from '@/shared/components/ui/input'
import { Label } from '@/shared/components/ui/label'

/** Sentinelle « tous les clients » : Radix refuse une option de valeur vide. */
export const ALL_CUSTOMERS = 'all'

export interface InvoiceFilterState {
  customerId: string
  search: string
  status?: string
  invoiceDateFrom: string
  invoiceDateTo: string
}

interface InvoiceFilterBarProps {
  value: InvoiceFilterState
  onChange: (next: InvoiceFilterState) => void
}

/**
 * Les filtres de la liste des factures.
 *
 * Aucune date n'est imposée, contrairement aux tournées : une facture se
 * cherche par client ou par numéro bien plus souvent que par jour, et forcer
 * une date cacherait le brouillon qu'on vient de laisser en plan.
 */
export function InvoiceFilterBar({ value, onChange }: InvoiceFilterBarProps) {
  const { t } = useTranslation()
  const customers = useCustomerList({ page: 1, perPage: 100 })

  const set = (patch: Partial<InvoiceFilterState>) => onChange({ ...value, ...patch })

  return (
    <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:flex-wrap">
      <div className="w-full sm:w-56">
        <AsyncSelect
          label={t('billing.invoices.filters.customer')}
          value={value.customerId}
          onChange={(customerId) => set({ customerId })}
          options={[
            { value: ALL_CUSTOMERS, label: t('billing.invoices.filters.allCustomers') },
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
        <Label htmlFor="invoice-date-from">{t('billing.invoices.filters.dateFrom')}</Label>
        <Input
          id="invoice-date-from"
          type="date"
          value={value.invoiceDateFrom}
          onChange={(event) => set({ invoiceDateFrom: event.target.value })}
          className="w-44"
        />
      </div>

      <div className="flex flex-col gap-2">
        <Label htmlFor="invoice-date-to">{t('billing.invoices.filters.dateTo')}</Label>
        <Input
          id="invoice-date-to"
          type="date"
          value={value.invoiceDateTo}
          onChange={(event) => set({ invoiceDateTo: event.target.value })}
          className="w-44"
        />
      </div>

      <SearchInput value={value.search} onChange={(search) => set({ search })} />

      <StatusFilterSelect source="invoice" value={value.status} onChange={(status) => set({ status })} />
    </div>
  )
}
