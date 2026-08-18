import { X } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import { ORDER_SOURCES, ORDER_STATUSES, type OrderFilters } from '../types/order'
import { SearchInput } from '@/shared/components/data/SearchInput'
import { Button } from '@/shared/components/ui/button'
import { Input } from '@/shared/components/ui/input'
import { Label } from '@/shared/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/shared/components/ui/select'

interface OrderFiltersProps {
  filters: OrderFilters
  onChange: (patch: Partial<OrderFilters>) => void
  onReset: () => void
}

/**
 * Filtres de la liste des commandes.
 *
 * Strictement ceux qu'accepte `ListOrderRequest`. Ni `priority` ni plage de
 * dates : ils n'existent pas côté serveur, et les proposer donnerait
 * l'illusion d'un filtrage qui n'a pas lieu.
 *
 * `requestedDate` est une date **unique**, pas un intervalle.
 */
export function OrderFilterBar({ filters, onChange, onReset }: OrderFiltersProps) {
  const { t } = useTranslation()

  const active =
    Boolean(filters.search) ||
    Boolean(filters.status) ||
    Boolean(filters.source) ||
    Boolean(filters.requestedDate) ||
    Boolean(filters.city)

  return (
    <div className="flex flex-col gap-4 rounded-lg border bg-card p-4">
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div className="flex flex-col gap-2 sm:col-span-2 lg:col-span-1">
          <Label>{t('common.search')}</Label>
          <SearchInput
            value={filters.search ?? ''}
            onChange={(search) => onChange({ search: search || undefined })}
            placeholder={t('orders.searchPlaceholder')}
          />
        </div>

        <div className="flex flex-col gap-2">
          <Label>{t('orders.fields.status')}</Label>
          <Select
            value={filters.status ?? 'all'}
            onValueChange={(value) => onChange({ status: value === 'all' ? undefined : value })}
          >
            <SelectTrigger className="w-full">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">{t('common.all')}</SelectItem>
              {ORDER_STATUSES.map((status) => (
                <SelectItem key={status} value={status}>
                  {t(`orderStatuses.${status}`, { defaultValue: status })}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        <div className="flex flex-col gap-2">
          <Label>{t('orders.fields.source')}</Label>
          <Select
            value={filters.source ?? 'all'}
            onValueChange={(value) => onChange({ source: value === 'all' ? undefined : value })}
          >
            <SelectTrigger className="w-full">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">{t('common.all')}</SelectItem>
              {ORDER_SOURCES.map((source) => (
                <SelectItem key={source} value={source}>
                  {t(`orderSources.${source}`, { defaultValue: source })}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        <div className="flex flex-col gap-2">
          <Label>{t('orders.fields.requestedDate')}</Label>
          <Input
            type="date"
            value={filters.requestedDate ?? ''}
            onChange={(event) => onChange({ requestedDate: event.target.value || undefined })}
          />
        </div>
      </div>

      {active ? (
        <div className="flex justify-end">
          <Button variant="ghost" size="sm" onClick={onReset}>
            <X className="size-4" aria-hidden />
            {t('common.reset')}
          </Button>
        </div>
      ) : null}
    </div>
  )
}
