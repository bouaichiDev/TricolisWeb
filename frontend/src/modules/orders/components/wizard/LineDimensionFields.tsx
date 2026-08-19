import { useTranslation } from 'react-i18next'

import { ControlledField } from '@/shared/components/form/ControlledField'

import type { LineDraft } from '../../schemas/orderDraft'
import { fieldError, type OrderIssue } from '../../schemas/orderErrors'

/**
 * Dimensions et prix d'une ligne.
 *
 * `StoreOrderRequest` accepte ces cinq champs sur une ligne, mais pas sur un
 * colis : la différence vient du serveur, pas d'un choix d'écran.
 */
const FIELDS = [
  ['length', 'orders.fields.length'],
  ['width', 'orders.fields.width'],
  ['height', 'orders.fields.height'],
  ['purchasePrice', 'orders.fields.purchasePrice'],
  ['sellingPrice', 'orders.fields.sellingPrice'],
] as const

interface LineDimensionFieldsProps {
  line: LineDraft
  issues: OrderIssue[]
  onChange: (values: Partial<LineDraft>) => void
}

export function LineDimensionFields({ line, issues, onChange }: LineDimensionFieldsProps) {
  const { t } = useTranslation()

  return (
    <fieldset className="mt-4 border-t pt-4">
      <legend className="mb-3 text-sm font-medium">{t('orders.lines.dimensions')}</legend>
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {FIELDS.map(([name, labelKey]) => (
          <ControlledField
            key={name}
            label={t(labelKey)}
            type="number"
            min="0"
            step="0.001"
            value={line[name]}
            onChange={(value) => onChange({ [name]: value } as Partial<LineDraft>)}
            error={fieldError(issues, name)}
          />
        ))}
      </div>
    </fieldset>
  )
}
