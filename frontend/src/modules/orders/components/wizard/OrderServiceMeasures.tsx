import { useTranslation } from 'react-i18next'

import { ControlledField } from '@/shared/components/form/ControlledField'

import type { ServiceDraft } from '../../schemas/orderDraft'
import { fieldError, type OrderIssue } from '../../schemas/orderErrors'

interface OrderServiceMeasuresProps {
  service: ServiceDraft
  issues: OrderIssue[]
  onChange: (values: Partial<ServiceDraft>) => void
}

/**
 * Créneau, mesures et montants d'un service.
 *
 * Les quatre montants sont `required` côté serveur ; le §29 interdit d'y poser
 * `0` en douce, ils sont donc saisis comme les autres champs obligatoires.
 */
export function OrderServiceMeasures({
  service,
  issues,
  onChange,
}: OrderServiceMeasuresProps) {
  const { t } = useTranslation()

  const field = (
    name: keyof ServiceDraft,
    label: string,
    type: 'text' | 'number' | 'date',
    required = true,
  ) => (
    <ControlledField
      key={name}
      label={label}
      type={type}
      min={type === 'number' ? '0' : undefined}
      step={type === 'number' ? '0.001' : undefined}
      value={service[name] as string}
      onChange={(value) => onChange({ [name]: value } as Partial<ServiceDraft>)}
      required={required}
      error={fieldError(issues, name)}
    />
  )

  return (
    <>
      <fieldset className="mt-4 border-t pt-4">
        <legend className="mb-3 text-sm font-medium">{t('orders.services.timing')}</legend>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {field('requestedDate', t('orders.fields.requestedDate'), 'date')}
          {field('requestedFrom', t('orders.fields.requestedFrom'), 'date', false)}
          {field('requestedTo', t('orders.fields.requestedTo'), 'date', false)}
          {field('requiredTimeMinutes', t('orders.fields.requiredTimeMinutes'), 'number')}
          {field('remainingTimeMinutes', t('orders.fields.remainingTimeMinutes'), 'number')}
        </div>
      </fieldset>

      <fieldset className="mt-4 border-t pt-4">
        <legend className="mb-3 text-sm font-medium">{t('orders.fields.content')}</legend>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {field('quantity', t('orders.fields.quantity'), 'number')}
          {field('unit', t('orders.fields.unit'), 'text')}
          {field('weight', t('orders.fields.weight'), 'number')}
          {field('volume', t('orders.fields.volume'), 'number')}
          {field('packageCount', t('orders.fields.packageCount'), 'number')}
        </div>
      </fieldset>

      <fieldset className="mt-4 border-t pt-4">
        <legend className="mb-1 text-sm font-medium">{t('orders.services.pricing')}</legend>
        <p className="mb-3 text-xs text-muted-foreground">{t('orders.services.pricingHint')}</p>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {field('customerUnitPrice', t('orders.fields.customerUnitPrice'), 'number')}
          {field('customerTotalPrice', t('orders.fields.customerTotalPrice'), 'number')}
          {field('providerUnitCost', t('orders.fields.providerUnitCost'), 'number')}
          {field('providerTotalCost', t('orders.fields.providerTotalCost'), 'number')}
        </div>
      </fieldset>
    </>
  )
}
