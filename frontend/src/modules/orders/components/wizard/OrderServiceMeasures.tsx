import { useTranslation } from 'react-i18next'

import { ControlledField } from '@/shared/components/form/ControlledField'

import type { ServiceDraft } from '../../schemas/orderDraft'
import { fieldError, type OrderIssue } from '../../schemas/orderErrors'

interface OrderServiceMeasuresProps {
  service: ServiceDraft
  issues: OrderIssue[]
  onChange: (values: Partial<ServiceDraft>) => void
  /** Drapeaux du service du référentiel, quand un service est choisi. */
  billableToCustomer?: boolean
  payableToProvider?: boolean
}

/**
 * Créneau, mesures et montants d'un service.
 *
 * **À quoi servent les montants** : ils portent la valeur commerciale de la
 * prestation — ce que le client paie d'un côté, ce que le prestataire coûte de
 * l'autre. Les quatre sont `required` côté serveur ; le §29 interdit d'y poser
 * `0` en douce, ils sont donc saisis.
 *
 * Les totaux se déduisent du prix unitaire et de la quantité, tout en restant
 * modifiables : une remise ou un forfait s'écrivent à la main.
 *
 * `billableToCustomer` et `payableToProvider` viennent du référentiel : quand
 * un service n'est pas facturable, l'écran le dit plutôt que de laisser saisir
 * un montant qui ne sera jamais facturé.
 */
export function OrderServiceMeasures({
  service,
  issues,
  onChange,
  billableToCustomer,
  payableToProvider,
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
        <p className="text-xs text-muted-foreground">{t('orders.services.pricingHint')}</p>
        {billableToCustomer === false ? (
          <p className="text-xs text-muted-foreground">{t('orders.services.notBillable')}</p>
        ) : null}
        {payableToProvider === false ? (
          <p className="text-xs text-muted-foreground">{t('orders.services.notPayable')}</p>
        ) : null}
        <div className="mb-3" />
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
