import { X } from 'lucide-react'

import { SearchInput } from '@/shared/components/data/SearchInput'
import { Button } from '@/shared/components/ui/button'
import { Input } from '@/shared/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/shared/components/ui/select'

import type { ClaimFilters } from '../types'

const ALL = '__all__'

interface ClaimFiltersBarProps {
  filters: ClaimFilters
  onChange: (patch: Partial<ClaimFilters>) => void
  onReset: () => void
}

/**
 * Recherche (titre et description), type, état. « Ouverte » et « clôturée »
 * se lisent sur la date de clôture — la seule règle d'état du module.
 */
export function ClaimFiltersBar({ filters, onChange, onReset }: ClaimFiltersBarProps) {
  const filtered = Boolean(filters.search || filters.claimType || filters.state)

  return (
    <div className="flex flex-col gap-3 lg:flex-row lg:flex-wrap lg:items-center">
      <SearchInput value={filters.search ?? ''} onChange={(search) => onChange({ search })} placeholder="Titre ou description…" />
      <Input
        aria-label="Type"
        placeholder="Type"
        className="w-full lg:w-44"
        value={filters.claimType ?? ''}
        onChange={(event) => onChange({ claimType: event.target.value })}
      />
      <Select value={filters.state || ALL} onValueChange={(value) => onChange({ state: value === ALL ? '' : (value as 'open' | 'closed') })}>
        <SelectTrigger className="w-full lg:w-44" aria-label="État">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value={ALL}>Toutes</SelectItem>
          <SelectItem value="open">Ouvertes</SelectItem>
          <SelectItem value="closed">Clôturées</SelectItem>
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
