import { zodResolver } from '@hookform/resolvers/zod'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'

import { ReferentialStatusSelect } from '@/modules/statuses/components/ReferentialStatusSelect'
import { AsyncSelect } from '@/shared/components/form/AsyncSelect'
import { FormActions } from '@/shared/components/form/FormActions'
import { FormErrorSummary } from '@/shared/components/form/FormErrorSummary'
import { TextField } from '@/shared/components/form/TextField'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { useApiFormError } from '@/shared/hooks/useApiForm'

import { AvailabilityHint } from './AvailabilityHint'
import { CustomerFilterSelect } from '@/modules/customers/components/CustomerFilterSelect'
import { useCustomerOrderOptions, useOrderLineOptions } from '../hooks/useOrderLineOptions'
import { useStockItemOptions } from '../hooks/useStockScope'
import {
  STOCK_RESERVATION_FORM_DEFAULTS,
  stockReservationSchema,
  type StockReservationFormValues,
} from '../schemas/stockReservationSchema'
import { STOCK_RESERVATION_SOURCE } from '../utils/stockSources'

interface StockReservationFormProps {
  onSubmit: (values: StockReservationFormValues) => Promise<unknown>
  onCancel: () => void
  submitLabel: string
}

/**
 * Réserver du stock pour une ligne de commande.
 *
 * Le parcours suit la contrainte du serveur, il ne l'invente pas :
 * `OrderLine.order.customerId` doit valoir `StockItem.customerId`. Le client se
 * choisit donc en premier, et tout le reste — commandes, lignes, articles — en
 * découle. Un ordre différent laisserait composer une réservation que le
 * serveur refuserait.
 *
 * L'emplacement se choisit **après** l'article, parce que c'est l'article qui
 * dit où il en reste : `AvailabilityHint` liste les emplacements qui en portent,
 * avec le disponible de chacun.
 *
 * Ce disponible n'est pas vérifié ici. Il change entre l'affichage et l'envoi,
 * et `CreateStockReservationAction` le relit sous verrou : c'est le 409 qui
 * tranche, pas l'écran.
 */
export function StockReservationForm({
  onSubmit,
  onCancel,
  submitLabel,
}: StockReservationFormProps) {
  const { t } = useTranslation()

  const [customerId, setCustomerId] = useState<string | undefined>(undefined)
  const [orderId, setOrderId] = useState('')

  const orders = useCustomerOrderOptions(customerId ?? '')
  const lines = useOrderLineOptions(orderId)
  const items = useStockItemOptions(customerId ?? '')

  const form = useForm<StockReservationFormValues>({
    resolver: zodResolver(stockReservationSchema),
    defaultValues: STOCK_RESERVATION_FORM_DEFAULTS,
  })

  const { formError, handleError, clearError } = useApiFormError(form)
  const stockItemId = form.watch('stockItemId')

  const reset = () => {
    form.setValue('orderLineId', '')
    form.setValue('stockItemId', '')
    form.setValue('stockLocationId', '')
  }

  const submit = form.handleSubmit(async (values) => {
    clearError()
    try {
      await onSubmit(values)
    } catch (error) {
      handleError(error)
    }
  })

  return (
    <form onSubmit={submit} className="flex flex-col gap-6" noValidate>
      <FormErrorSummary message={formError} />

      <SectionCard title={t('stock.sections.forWhom')} description={t('stock.reservationWhoHint')}>
        <div className="grid gap-5 sm:grid-cols-2">
          <div className="flex flex-col gap-2">
            <span className="text-sm font-medium">{t('stock.fields.customer')}</span>
            <CustomerFilterSelect
              value={customerId}
              onChange={(next) => {
                setCustomerId(next)
                setOrderId('')
                reset()
              }}
              className="w-full"
            />
            <p className="text-xs text-muted-foreground">{t('stock.reservationCustomerHint')}</p>
          </div>

          <AsyncSelect
            label={t('stock.fields.order')}
            value={orderId}
            onChange={(next) => {
              setOrderId(next)
              form.setValue('orderLineId', '')
            }}
            options={orders.options}
            isLoading={orders.isLoading}
            disabled={customerId === undefined}
            description={customerId === undefined ? t('stock.pickCustomerFirst') : undefined}
            required
          />

          <AsyncSelect
            label={t('stock.fields.orderLine')}
            value={form.watch('orderLineId')}
            onChange={(next) =>
              form.setValue('orderLineId', next, { shouldDirty: true, shouldValidate: true })
            }
            options={lines.options}
            isLoading={lines.isLoading}
            disabled={orderId === ''}
            description={orderId === '' ? t('stock.pickOrderFirst') : t('stock.orderLineHint')}
            required
            error={form.formState.errors.orderLineId?.message}
          />
        </div>
      </SectionCard>

      <SectionCard title={t('stock.sections.whatStock')} description={t('stock.reservationWhatHint')}>
        <div className="grid gap-5 sm:grid-cols-2">
          <AsyncSelect
            label={t('stock.fields.articleCode')}
            value={stockItemId}
            onChange={(next) => {
              form.setValue('stockItemId', next, { shouldDirty: true, shouldValidate: true })
              // L'article change : l'emplacement retenu n'en porte peut-être pas.
              form.setValue('stockLocationId', '')
            }}
            options={items.options}
            isLoading={items.isLoading}
            disabled={customerId === undefined}
            description={customerId === undefined ? t('stock.pickCustomerFirst') : undefined}
            required
            error={form.formState.errors.stockItemId?.message}
          />

          <AvailabilityHint
            stockItemId={stockItemId}
            value={form.watch('stockLocationId')}
            onChange={(next) =>
              form.setValue('stockLocationId', next, { shouldDirty: true, shouldValidate: true })
            }
            error={form.formState.errors.stockLocationId?.message}
          />

          <TextField
            form={form}
            name="quantity"
            label={t('stock.fields.quantity')}
            type="number"
            required
          />

          <ReferentialStatusSelect
            form={form}
            name="status"
            label={t('stock.fields.status')}
            source={STOCK_RESERVATION_SOURCE}
          />
        </div>
      </SectionCard>

      <FormActions
        onCancel={onCancel}
        submitLabel={submitLabel}
        isSubmitting={form.formState.isSubmitting}
      />
    </form>
  )
}
