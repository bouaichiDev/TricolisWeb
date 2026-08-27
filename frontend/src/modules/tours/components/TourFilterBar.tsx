import { useTranslation } from 'react-i18next'

import { useCustomerList } from '@/modules/customers/hooks/useCustomers'
import { StatusFilterSelect } from '@/modules/statuses/components/StatusFilterSelect'
import { SearchInput } from '@/shared/components/data/SearchInput'
import { AsyncSelect } from '@/shared/components/form/AsyncSelect'
import { Input } from '@/shared/components/ui/input'
import { Label } from '@/shared/components/ui/label'

/** Sentinelle « tous les clients » : Radix refuse une option de valeur vide. */
export const ALL_CUSTOMERS = 'all'

interface TourFilterBarProps {
  date: string
  onDateChange: (date: string) => void
  customerId: string
  onCustomerChange: (customerId: string) => void
  search: string
  onSearchChange: (search: string) => void
  status?: string
  onStatusChange: (status?: string) => void
}

/**
 * Les filtres de la liste des tournées.
 *
 * **La date est obligatoire**, décision du 27 août 2026 : une tournée se lit
 * par jour. Sans date, la liste mélange un mois de préparation et on ne compare
 * plus rien. Le champ ne peut donc pas être vidé — il revient au jour courant.
 *
 * Le client ne filtre **que les commandes à planifier** : une tournée n'a pas de
 * client, elle en dessert plusieurs, et le serveur n'expose aucun filtre client
 * sur `/tours`. Le prétendre ici donnerait un filtre sans effet.
 */
export function TourFilterBar({
  date,
  onDateChange,
  customerId,
  onCustomerChange,
  search,
  onSearchChange,
  status,
  onStatusChange,
}: TourFilterBarProps) {
  const { t } = useTranslation()

  const customers = useCustomerList({ page: 1, perPage: 100 })

  return (
    <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
      <div className="flex flex-col gap-2">
        <Label htmlFor="tour-date">{t('tours.fields.tourDate')}</Label>
        <Input
          id="tour-date"
          type="date"
          value={date}
          required
          // Vider le champ ramene au jour courant : une liste sans date
          // melangerait un mois de tournees et ne se comparerait plus.
          onChange={(event) =>
            onDateChange(event.target.value === '' ? todayIso() : event.target.value)
          }
          className="w-44"
        />
      </div>

      <div className="w-full sm:w-56">
        <AsyncSelect
          label={t('tours.filters.customer')}
          value={customerId}
          onChange={onCustomerChange}
          options={[
            { value: ALL_CUSTOMERS, label: t('tours.filters.allCustomers') },
            ...(customers.data?.data ?? []).map((customer) => ({
              value: customer.id,
              label: customer.name,
              hint: customer.code,
            })),
          ]}
          isLoading={customers.isPending}
          description={t('tours.filters.customerHint')}
        />
      </div>

      <SearchInput value={search} onChange={onSearchChange} />

      <StatusFilterSelect source="tour" value={status} onChange={onStatusChange} />
    </div>
  )
}

/** Le jour courant au format que l'API et l'input attendent. */
export function todayIso(): string {
  return new Date().toISOString().slice(0, 10)
}
