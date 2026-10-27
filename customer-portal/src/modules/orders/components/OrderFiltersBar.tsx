import { X } from 'lucide-react'

import { SearchInput } from '@/shared/components/data/SearchInput'
import { Button } from '@/shared/components/ui/button'
import { Input } from '@/shared/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/shared/components/ui/select'

import type { FormOptions, OrderFilters } from '../types'

interface OrderFiltersBarProps {
  filters: OrderFilters
  options: FormOptions | undefined
  onChange: (patch: Partial<OrderFilters>) => void
  onReset: () => void
}

/** Valeur « tous » : Radix refuse une option à valeur vide. */
const ALL = '__all__'

/** Filtres combinables, présentés comme ceux des listes du back-office. */
export function OrderFiltersBar({ filters, options, onChange, onReset }: OrderFiltersBarProps) {
  const filtered = Boolean(filters.search || filters.status || filters.service || filters.dateFrom || filters.dateTo)

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
      <div className="flex items-center gap-2">
        <Input type="date" aria-label="Date de début" className="w-full lg:w-40" value={filters.dateFrom ?? ''} onChange={(event) => onChange({ dateFrom: event.target.value })} />
        <span className="text-muted-foreground text-sm">→</span>
        <Input type="date" aria-label="Date de fin" className="w-full lg:w-40" value={filters.dateTo ?? ''} onChange={(event) => onChange({ dateTo: event.target.value })} />
      </div>
      {filtered && (
        <Button variant="ghost" size="sm" onClick={onReset}>
          <X className="size-4" aria-hidden /> Réinitialiser
        </Button>
      )}
    </div>
  )
}
