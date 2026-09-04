import { useTranslation } from 'react-i18next'

import { useAgencyList } from '../hooks/useAgencies'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/shared/components/ui/select'

interface AgencyPickerProps {
  value: string
  onChange: (agencyId: string) => void
  className?: string
}

/**
 * Choix d'une agence, hors formulaire.
 *
 * Sert de premier maillon aux ecrans en cascade : sans agence selectionnee, il
 * n'y a aucune route depot a appeler, l'API ne les exposant que sous
 * `/agencies/{agency}/depots`.
 */
export function AgencyPicker({ value, onChange, className }: AgencyPickerProps) {
  const { t } = useTranslation()
  const { data, isPending } = useAgencyList({ page: 1, perPage: 100, sort: 'name', direction: 'asc' })

  const agencies = data?.data ?? []

  return (
    <Select value={value} onValueChange={onChange} disabled={isPending || agencies.length === 0}>
      <SelectTrigger className={className} aria-label={t('depots.fields.agency')}>
        <SelectValue placeholder={t('depots.selectAgency')} />
      </SelectTrigger>
      <SelectContent>
        {agencies.map((agency) => (
          <SelectItem key={agency.id} value={agency.id}>
            {agency.name}
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  )
}
