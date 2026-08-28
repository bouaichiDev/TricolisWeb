import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import type { BillableColumnFilters } from './billableFilters'
import { useBillableSuggestions } from '../hooks/useInvoices'
import { AutocompleteInput } from '@/shared/components/form/AutocompleteInput'
import { MultiAutocompleteInput } from '@/shared/components/form/MultiAutocompleteInput'
import { Input } from '@/shared/components/ui/input'

interface FieldProps {
  value: BillableColumnFilters
  onChange: (patch: Partial<BillableColumnFilters>) => void
}

/**
 * Les champs de filtre posés sous les en-têtes du sélecteur.
 *
 * Ils tiennent sur **une seule ligne**, alignés avec leur colonne : empilés,
 * ils décalaient le tableau et l'on ne savait plus quel champ filtrait quoi.
 *
 * Chacun porte un `aria-label` : sous un en-tête, un champ n'a pas d'étiquette
 * visible, et sans lui il resterait muet pour un lecteur d'écran.
 */

/**
 * La colonne « Prestation » : plusieurs valeurs, complétées par ce qui existe.
 *
 * La saisie en cours vit ici et non dans l'état des filtres : elle ne filtre
 * rien tant qu'elle n'est pas retenue, et la remonter ferait repartir une
 * requête de liste à chaque frappe.
 */
export function ServiceFilter({
  customerId,
  label,
  value,
  onChange,
}: FieldProps & { customerId: string; label: string }) {
  const [term, setTerm] = useState('')
  const { data, isFetching } = useBillableSuggestions(customerId, 'service', term)

  return (
    <MultiAutocompleteInput
      values={value.service}
      onChange={(next) => onChange({ service: next })}
      term={term}
      onTermChange={setTerm}
      suggestions={data ?? []}
      isLoading={isFetching}
      label={label}
      className="h-8 min-w-36"
    />
  )
}

/** Numéro de commande, complété par ce qui existe. */
export function NumberFilter({
  customerId,
  label,
  value,
  onChange,
}: FieldProps & { customerId: string; label: string }) {
  const { data, isFetching } = useBillableSuggestions(customerId, 'order', value.order)

  return (
    <AutocompleteInput
      value={value.order}
      onChange={(next) => onChange({ order: next })}
      suggestions={data ?? []}
      isLoading={isFetching}
      label={label}
      className="h-8 min-w-32"
    />
  )
}

export function TextFilter({
  field,
  label,
  value,
  onChange,
}: FieldProps & { field: 'reference' | 'address'; label: string }) {
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
    <div className="flex items-center gap-1">
      <Input
        type="date"
        value={value.periodFrom}
        onChange={(event) => onChange({ periodFrom: event.target.value })}
        aria-label={t('billing.invoices.picker.periodFrom')}
        className="h-8 w-36"
      />
      <Input
        type="date"
        value={value.periodTo}
        onChange={(event) => onChange({ periodTo: event.target.value })}
        aria-label={t('billing.invoices.picker.periodTo')}
        className="h-8 w-36"
      />
    </div>
  )
}

/**
 * Un intervalle plutôt qu'une égalité : un prix décimal ne se saisit pas
 * exactement, et « au moins 100 » est la question qu'on se pose vraiment.
 */
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
    <div className="flex items-center gap-1">
      <Input
        type="number"
        min={0}
        step="0.01"
        value={value[min]}
        onChange={(event) => onChange({ [min]: event.target.value })}
        aria-label={labels.min}
        className="h-8 w-20"
      />
      <Input
        type="number"
        min={0}
        step="0.01"
        value={value[max]}
        onChange={(event) => onChange({ [max]: event.target.value })}
        aria-label={labels.max}
        className="h-8 w-20"
      />
    </div>
  )
}
