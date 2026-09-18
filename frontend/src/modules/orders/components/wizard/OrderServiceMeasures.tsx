import { useTranslation } from 'react-i18next'

import { ControlledField } from '@/shared/components/form/ControlledField'

import type { ServiceDraft } from '../../schemas/orderDraft'
import { fieldError, type OrderIssue } from '../../schemas/orderErrors'
import type { ServiceContent } from '../../schemas/packageMeasures'

interface OrderServiceMeasuresProps {
  service: ServiceDraft
  /** Poids, volume et colis déduits des colis cochés plus bas. */
  content: ServiceContent
  issues: OrderIssue[]
  onChange: (values: Partial<ServiceDraft>) => void
  /** Drapeaux du service du référentiel, quand un service est choisi. */
  billableToCustomer?: boolean
  payableToProvider?: boolean
}

/**
 * Créneau, contenu, montants et consignes d'un service.
 *
 * **Le contenu se calcule** : poids, volume et nombre de colis suivent les
 * colis que le service prend en charge. Quantité et unité restent saisies —
 * elles disent ce qui est facturé, et l'unité vient du référentiel.
 *
 * **Les montants sont facultatifs** : un service se planifie souvent avant
 * d'être chiffré. Laissés vides, ils ne sont pas envoyés. Les totaux se
 * déduisent du prix unitaire et de la quantité, tout en restant modifiables :
 * une remise ou un forfait s'écrivent à la main.
 *
 * `billableToCustomer` et `payableToProvider` viennent du référentiel : quand
 * un service n'est pas facturable, l'écran le dit plutôt que de laisser saisir
 * un montant qui ne sera jamais facturé.
 */
export function OrderServiceMeasures({
  service,
  content,
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

  const computed = (key: keyof ServiceContent, label: string) => (
    <ControlledField
      key={key}
      label={label}
      type="number"
      value={String(content[key])}
      onChange={() => undefined}
      disabled
      description={t('orders.services.contentHint')}
      error={fieldError(issues, key)}
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
          {computed('weight', t('orders.fields.weight'))}
          {computed('volume', t('orders.fields.volume'))}
          {computed('packageCount', t('orders.fields.packageCount'))}
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
          {field('customerUnitPrice', t('orders.fields.customerUnitPrice'), 'number', false)}
          {field('customerTotalPrice', t('orders.fields.customerTotalPrice'), 'number', false)}
          {field('providerUnitCost', t('orders.fields.providerUnitCost'), 'number', false)}
          {field('providerTotalCost', t('orders.fields.providerTotalCost'), 'number', false)}
        </div>
      </fieldset>

      <div className="mt-4 border-t pt-4">
        <ControlledField
          label={t('orders.fields.instructions')}
          value={service.instructions}
          onChange={(instructions) => onChange({ instructions })}
          multiline
          error={fieldError(issues, 'instructions')}
        />
      </div>
    </>
  )
}
