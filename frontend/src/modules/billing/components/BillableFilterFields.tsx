import { useTranslation } from 'react-i18next'

import type { BillableColumnFilters } from './billableFilters'
import { Input } from '@/shared/components/ui/input'

interface FieldProps {
  value: BillableColumnFilters
  onChange: (patch: Partial<BillableColumnFilters>) => void
}

/**
 * Les champs de filtre posés sous les en-têtes du sélecteur.
 *
 * Ils sont ici plutôt que dans le tableau : une colonne déclare son contrôle,
 * et le tableau ne fait que le placer. Chacun porte un `aria-label` — un champ
 * sous un en-tête n'a pas d'étiquette visible, et sans lui il resterait muet
 * pour un lecteur d'écran comme pour un test.
 */
export function TextFilter({
  field,
  label,
  value,
  onChange,
}: FieldProps & { field: 'service' | 'order' | 'address'; label: string }) {
  return (
    <Input
      value={value[field]}
      onChange={(event) => onChange({ [field]: event.target.value })}
      aria-label={label}
      className="h-8 min-w-28"
    />
  )
}

/** Deux bornes de date : une facture couvre une période, pas un jour. */
export function PeriodFilter({ value, onChange }: FieldProps) {
  const { t } = useTranslation()

  return (
    <div className="flex flex-col gap-1">
      <Input
        type="date"
        value={value.periodFrom}
        onChange={(event) => onChange({ periodFrom: event.target.value })}
        aria-label={t('billing.invoices.picker.periodFrom')}
        className="h-8 min-w-36"
      />
      <Input
        type="date"
        value={value.periodTo}
        onChange={(event) => onChange({ periodTo: event.target.value })}
        aria-label={t('billing.invoices.picker.periodTo')}
        className="h-8 min-w-36"
      />
    </div>
  )
}

/** Un intervalle : « au moins » et « au plus », plutôt qu'une égalité exacte
 *  qu'on ne saurait pas saisir sur un décimal. */
export function RangeFilter({
  value,
  onChange,
  min,
  max,
  labels,
}: FieldProps & {
  min: 'quantityMin' | 'priceMin'
  max: 'quantityMax' | 'priceMax'
  labels: { min: string; max: string }
}) {
  return (
    <div className="flex flex-col gap-1">
      <Input
        type="number"
        min={0}
        step="0.01"
        value={value[min]}
        onChange={(event) => onChange({ [min]: event.target.value })}
        aria-label={labels.min}
        className="h-8 min-w-20"
      />
      <Input
        type="number"
        min={0}
        step="0.01"
        value={value[max]}
        onChange={(event) => onChange({ [max]: event.target.value })}
        aria-label={labels.max}
        className="h-8 min-w-20"
      />
    </div>
  )
}
