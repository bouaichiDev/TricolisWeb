import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/shared/components/ui/select'

const ALL = '__all__'

interface StatusFilterProps {
  value: string
  onChange: (value: string) => void
  options: { value: string; label: string }[]
}

/** Filtre de statut des listes : « Tous les statuts » ou un code du référentiel. */
export function StatusFilter({ value, onChange, options }: StatusFilterProps) {
  return (
    <Select value={value || ALL} onValueChange={(next) => onChange(next === ALL ? '' : next)}>
      <SelectTrigger className="w-full sm:w-48" aria-label="Filtrer par statut">
        <SelectValue />
      </SelectTrigger>
      <SelectContent>
        <SelectItem value={ALL}>Tous les statuts</SelectItem>
        {options.map((option) => (
          <SelectItem key={option.value} value={option.value}>
            {option.label}
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  )
}
