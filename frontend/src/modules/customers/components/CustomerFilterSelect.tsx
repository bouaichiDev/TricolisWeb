import { useTranslation } from 'react-i18next'

import { useCustomerOptions } from '@/modules/orders/hooks/useOrderScope'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/shared/components/ui/select'

interface CustomerFilterSelectProps {
  value: string | undefined
  onChange: (customerId: string | undefined) => void
  className?: string
}

/**
 * Filtre « client » des listes de stock.
 *
 * Calqué sur `StatusFilterSelect` : « Tous » vaut `undefined`, pas la chaîne
 * vide. `SelectItem` de Radix refuse une valeur vide, et un `customerId=` vide
 * partirait dans l'URL de l'API — où il serait refusé par la règle `ulid`.
 *
 * Le stock est physiquement celui du transporteur mais reste séparé par client :
 * ce filtre est donc la question la plus fréquente de ces écrans, pas un
 * raffinement.
 */
export function CustomerFilterSelect({ value, onChange, className }: CustomerFilterSelectProps) {
  const { t } = useTranslation()
  const customers = useCustomerOptions('')

  return (
    <Select
      value={value ?? 'all'}
      onValueChange={(next) => onChange(next === 'all' ? undefined : next)}
    >
      <SelectTrigger
        className={className ?? 'w-full sm:w-56'}
        aria-label={t('stock.fields.customer')}
      >
        <SelectValue placeholder={t('stock.fields.customer')} />
      </SelectTrigger>

      <SelectContent>
        <SelectItem value="all">{t('stock.allCustomers')}</SelectItem>
        {customers.options.map((option) => (
          <SelectItem key={option.value} value={option.value}>
            {option.label}
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  )
}
