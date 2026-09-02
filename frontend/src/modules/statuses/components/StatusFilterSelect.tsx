import { useTranslation } from 'react-i18next'

import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/shared/components/ui/select'

import { useStatusOptions } from '../hooks/useStatuses'

interface StatusFilterSelectProps {
  source: string
  value: string | undefined
  onChange: (status: string | undefined) => void
  className?: string
  /** Identifiant du declencheur, pour qu'un libelle visible s'y associe. */
  id?: string
}

/**
 * Filtre de statut alimenté par le référentiel.
 *
 * Les listes de Phase 1 codaient leurs statuts en dur ; celles de cette phase
 * les demandent à `statuses`, de sorte qu'un code ajouté par un administrateur
 * devienne filtrable sans toucher au frontend.
 *
 * « Tous » vaut `undefined`, pas la chaîne vide : `SelectItem` de Radix refuse
 * une valeur vide, et un `status=` vide partirait dans l'URL de l'API.
 */
export function StatusFilterSelect({
  source,
  value,
  onChange,
  className,
  id,
}: StatusFilterSelectProps) {
  const { t } = useTranslation()
  const { options } = useStatusOptions(source, value)

  return (
    <Select
      value={value ?? 'all'}
      onValueChange={(next) => onChange(next === 'all' ? undefined : next)}
    >
      <SelectTrigger
        id={id}
        className={className ?? 'w-full sm:w-48'}
        aria-label={t('statuses.filter')}
      >
        <SelectValue placeholder={t('statuses.filter')} />
      </SelectTrigger>

      <SelectContent>
        <SelectItem value="all">{t('common.all')}</SelectItem>
        {options.map((option) => (
          <SelectItem key={option.value} value={option.value}>
            {option.label}
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  )
}
