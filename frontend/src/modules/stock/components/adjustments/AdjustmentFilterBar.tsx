import { useTranslation } from 'react-i18next'

import { CustomerFilterSelect } from '@/modules/customers/components/CustomerFilterSelect'
import { StatusFilterSelect } from '@/modules/statuses/components/StatusFilterSelect'
import { SearchInput } from '@/shared/components/data/SearchInput'
import { Input } from '@/shared/components/ui/input'

import type { StockAdjustmentRequestFilters } from '../../types/stockAdjustment'
import { STOCK_ADJUSTMENT_REQUEST_SOURCE } from '../../utils/stockSources'

interface AdjustmentFilterBarProps {
  filters: StockAdjustmentRequestFilters
  onChange: (next: Partial<StockAdjustmentRequestFilters>) => void
  /** L'onglet de la fiche client porte déjà le client. */
  withCustomer?: boolean
}

/**
 * Filtres des demandes de correction : exactement ceux que
 * `ListStockAdjustmentRequestRequest` accepte. L'article se cherche par son
 * code, avec le motif, dans `search`.
 */
export function AdjustmentFilterBar({ filters, onChange, withCustomer = true }: AdjustmentFilterBarProps) {
  const { t } = useTranslation()

  return (
    <div className="flex flex-col gap-3 lg:flex-row lg:flex-wrap lg:items-center">
      <SearchInput
        value={filters.search ?? ''}
        onChange={(search) => onChange({ search: search || undefined })}
        placeholder={t('stock.adjustments.searchPlaceholder')}
        label={t('stock.adjustments.searchPlaceholder')}
      />
      <StatusFilterSelect
        source={STOCK_ADJUSTMENT_REQUEST_SOURCE}
        value={filters.status?.[0]}
        onChange={(status) => onChange({ status: status === undefined ? undefined : [status] })}
      />
      {withCustomer ? (
        <CustomerFilterSelect
          value={filters.customerId}
          onChange={(customerId) => onChange({ customerId })}
        />
      ) : null}
      <Input
        type="date"
        className="w-full sm:w-40"
        aria-label={t('stock.adjustments.createdFrom')}
        value={filters.createdFrom ?? ''}
        onChange={(event) => onChange({ createdFrom: event.target.value || undefined })}
      />
      <Input
        type="date"
        className="w-full sm:w-40"
        aria-label={t('stock.adjustments.createdTo')}
        value={filters.createdTo ?? ''}
        onChange={(event) => onChange({ createdTo: event.target.value || undefined })}
      />
    </div>
  )
}
