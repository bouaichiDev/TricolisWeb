import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router-dom'

import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { DetailSkeleton } from '@/shared/components/feedback/LoadingSkeleton'
import { AsyncSelect } from '@/shared/components/form/AsyncSelect'
import { ControlledField } from '@/shared/components/form/ControlledField'
import { FormErrorSummary } from '@/shared/components/form/FormErrorSummary'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { ApiError } from '@/shared/api/errors'
import { Alert, AlertDescription } from '@/shared/components/ui/alert'
import { Button } from '@/shared/components/ui/button'

import { useDepotOptions } from '../hooks/useOrderScope'
import { useOrder, useUpdateOrder } from '../hooks/useOrders'
import type { UpdateOrderPayload } from '../types/orderPayload'

type HeaderDraft = Record<keyof UpdateOrderPayload, string>

const blank = (value: string): string | null => (value.trim() === '' ? null : value.trim())

/**
 * Modification de l'en-tête d'une commande.
 *
 * Ni client ni agence : `UpdateOrderRequest` ne les accepte pas — une commande
 * ne change pas de périmètre après création. Le contenu — lignes, colis,
 * services — se modifie par ses propres routes, et seulement tant que
 * `allowsContentChanges` le permet.
 */
export function OrderEditPage() {
  const { t } = useTranslation()
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()

  const { data: order, isPending, error, refetch } = useOrder(id)
  const updateOrder = useUpdateOrder(id ?? '')
  // Le dépôt reste modifiable, mais toujours dans l'agence de la commande :
  // `OrderScopeGuard` refuse un dépôt rattaché à une autre agence.
  const depots = useDepotOptions(order?.agencyId ?? '')

  const [draft, setDraft] = useState<HeaderDraft | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})
  const [formError, setFormError] = useState<string | null>(null)

  if (isPending) return <DetailSkeleton />
  if (error) return <ErrorState error={error} onRetry={() => void refetch()} />
  if (!order) return null

  const values: HeaderDraft = draft ?? {
    depotId: order.depotId ?? '',
    externalReference: order.externalReference ?? '',
    customerReference: order.customerReference ?? '',
    orderType: order.orderType ?? '',
    groupCode: order.groupCode ?? '',
    orderDate: order.orderDate.slice(0, 10),
    currencyCode: order.currencyCode ?? '',
    internalRemark: order.internalRemark ?? '',
    workerRemark: order.workerRemark ?? '',
  }

  const patch = (partial: Partial<HeaderDraft>) => setDraft({ ...values, ...partial })

  const submit = () => {
    setFieldErrors({})
    setFormError(null)

    updateOrder.mutate(
      {
        depotId: blank(values.depotId),
        externalReference: blank(values.externalReference),
        customerReference: blank(values.customerReference),
        orderType: blank(values.orderType),
        groupCode: blank(values.groupCode),
        orderDate: values.orderDate,
        currencyCode: values.currencyCode.trim().toUpperCase(),
        internalRemark: blank(values.internalRemark),
        workerRemark: blank(values.workerRemark),
      },
      {
        onSuccess: () => navigate(`/orders/${order.id}`),
        onError: (cause) => {
          if (cause instanceof ApiError && cause.isValidation) {
            setFieldErrors(
              Object.fromEntries(
                Object.entries(cause.errors).map(([field, messages]) => [field, messages[0]]),
              ),
            )
            return
          }

          setFormError(cause instanceof ApiError ? cause.message : t('errors.unexpected'))
        },
      },
    )
  }

  const field = (name: keyof HeaderDraft, label: string, type: 'text' | 'date' = 'text') => (
    <ControlledField
      label={label}
      type={type}
      value={values[name]}
      onChange={(value) => patch({ [name]: value } as Partial<HeaderDraft>)}
      error={fieldErrors[name]}
    />
  )

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={t('orders.edit')} description={order.orderNumber} />

      <FormErrorSummary message={formError} />

      {!order.allowsContentChanges ? (
        <Alert>
          <AlertDescription>{t('orders.locked')}</AlertDescription>
        </Alert>
      ) : null}

      <SectionCard title={t('orders.review.header')}>
        <div className="grid gap-4 sm:grid-cols-2">
          <ControlledField
            label={t('orders.fields.customer')}
            value={order.customer?.name ?? ''}
            onChange={() => undefined}
            disabled
            description={t('orders.wizard.scopeLocked')}
          />
          <ControlledField
            label={t('orders.fields.agency')}
            value={order.agency?.name ?? ''}
            onChange={() => undefined}
            disabled
            description={t('orders.wizard.scopeLocked')}
          />
          <AsyncSelect
            label={t('orders.fields.depot')}
            value={values.depotId}
            onChange={(depotId) => patch({ depotId })}
            options={depots.options}
            isLoading={depots.isLoading}
            error={fieldErrors.depotId}
          />
          {field('orderDate', t('orders.fields.orderDate'), 'date')}
          {field('currencyCode', t('orders.fields.currencyCode'))}
          {field('externalReference', t('orders.fields.externalReference'))}
          {field('customerReference', t('orders.fields.customerReference'))}
          {field('orderType', t('orders.fields.orderType'))}
          {field('groupCode', t('orders.fields.groupCode'))}
        </div>
      </SectionCard>

      <SectionCard title={t('orders.fields.internalRemark')}>
        <div className="grid gap-4">
          <ControlledField
            label={t('orders.fields.internalRemark')}
            value={values.internalRemark}
            onChange={(internalRemark) => patch({ internalRemark })}
            multiline
            error={fieldErrors.internalRemark}
          />
          <ControlledField
            label={t('orders.fields.workerRemark')}
            value={values.workerRemark}
            onChange={(workerRemark) => patch({ workerRemark })}
            multiline
            error={fieldErrors.workerRemark}
          />
        </div>
      </SectionCard>

      <div className="flex justify-end gap-2">
        <Button variant="ghost" onClick={() => navigate(`/orders/${order.id}`)}>
          {t('common.cancel')}
        </Button>
        <Button onClick={submit} disabled={updateOrder.isPending}>
          {updateOrder.isPending ? t('common.saving') : t('common.save')}
        </Button>
      </div>
    </div>
  )
}
