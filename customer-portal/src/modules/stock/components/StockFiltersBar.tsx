import { X } from 'lucide-react'

import { SearchInput } from '@/shared/components/data/SearchInput'
import { Button } from '@/shared/components/ui/button'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/shared/components/ui/select'

import type { StockFilterOptions, StockFilters } from '../types'
import { locationLabel } from './stockColumns'

const ALL = '__all__'

interface StockFiltersBarProps {
  filters: StockFilters
  options: StockFilterOptions | undefined
  onChange: (patch: Partial<StockFilters>) => void
  onReset: () => void
}

/**
 * Recherche (code, code-barres, description), statut, emplacement. Statuts
 * du référentiel et emplacements où le client a du stock — tous deux lus au
 * backend, rien n'est écrit ici.
 */
export function StockFiltersBar({ filters, options, onChange, onReset }: StockFiltersBarProps) {
  const filtered = Boolean(filters.search || filters.status || filters.locationId)

  return (
    <div className="flex flex-col gap-3 lg:flex-row lg:flex-wrap lg:items-center">
      <SearchInput value={filters.search ?? ''} onChange={(search) => onChange({ search })} placeholder="Code, code-barres, description…" />
      <Select value={filters.status || ALL} onValueChange={(value) => onChange({ status: value === ALL ? '' : value })}>
        <SelectTrigger className="w-full lg:w-44" aria-label="Statut">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value={ALL}>Tous les statuts</SelectItem>
          {options?.statuses.map((status) => (
            <SelectItem key={status.code} value={status.code}>
              {status.label}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
      <Select value={filters.locationId || ALL} onValueChange={(value) => onChange({ locationId: value === ALL ? '' : value })}>
        <SelectTrigger className="w-full lg:w-64" aria-label="Emplacement">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value={ALL}>Tous les emplacements</SelectItem>
          {options?.locations.map((location) => (
            <SelectItem key={location.id} value={location.id}>
              {locationLabel(location)}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
      {filtered && (
        <Button variant="ghost" size="sm" onClick={onReset}>
          <X className="size-4" aria-hidden /> Réinitialiser
        </Button>
      )}
    </div>
  )
}
