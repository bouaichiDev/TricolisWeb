import { X } from 'lucide-react'

import { SearchInput } from '@/shared/components/data/SearchInput'
import { Button } from '@/shared/components/ui/button'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/shared/components/ui/select'

import type { CalendarFilterOptions, CalendarFilters } from '../types'

interface CalendarFiltersBarProps {
  filters: CalendarFilters
  options: CalendarFilterOptions | undefined
  onChange: (patch: Partial<CalendarFilters>) => void
  onReset: () => void
}

/** Valeur « tous » : Radix refuse une option à valeur vide. */
const ALL = '__all__'

/**
 * Statut, service, recherche (§36). Les options viennent du backend — statuts
 * du référentiel, services du transporteur — jamais d'une liste écrite ici.
 */
export function CalendarFiltersBar({ filters, options, onChange, onReset }: CalendarFiltersBarProps) {
  const filtered = Boolean(filters.search || filters.status || filters.service)

  return (
    <div className="flex flex-col gap-3 lg:flex-row lg:flex-wrap lg:items-center">
      <SearchInput value={filters.search ?? ''} onChange={(search) => onChange({ search })} placeholder="Référence, votre référence…" />
      <Select value={filters.status || ALL} onValueChange={(value) => onChange({ status: value === ALL ? '' : value })}>
        <SelectTrigger className="w-full lg:w-48" aria-label="Statut">
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
      <Select value={filters.service || ALL} onValueChange={(value) => onChange({ service: value === ALL ? '' : value })}>
        <SelectTrigger className="w-full lg:w-48" aria-label="Service">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value={ALL}>Tous les services</SelectItem>
          {options?.services.map((service) => (
            <SelectItem key={service.id} value={service.id}>
              {service.name}
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
