import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { ApiError } from '@/shared/api/errors'
import { AsyncSelect } from '@/shared/components/form/AsyncSelect'
import { ControlledField } from '@/shared/components/form/ControlledField'
import { Alert, AlertDescription } from '@/shared/components/ui/alert'
import { Button } from '@/shared/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/shared/components/ui/dialog'

import type { OrderServiceInput } from '../../api/orderContent.api'
import { useCreateOrderService, useUpdateOrderService } from '../../hooks/useOrderContent'
import { useServiceOptions } from '../../hooks/useServiceScope'
import type { OrderService } from '../../types/orderDetail'
import { fieldErrorsOf } from './formValues'
import { AddressPicker } from '../wizard/AddressPicker'
import {
  SERVICE_FIELDS,
  SERVICE_PRICE_FIELDS,
  serviceFormValues,
  type ServiceFieldSpec,
} from './orderServiceFields'

interface OrderServiceDialogProps {
  orderId: string
  customerId: string
  service: OrderService | null
  open: boolean
  onOpenChange: (open: boolean) => void
}

/**
 * Ajout ou correction d'un service d'une commande existante.
 *
 * C'est le service qui porte l'adresse, le créneau et les montants : le
 * modifier revient à modifier l'arrêt, puisqu'il n'existe pas d'entité
 * distincte. Les contacts et les colis pris en charge ont leurs propres routes
 * et ne sont pas repris ici.
 */
export function OrderServiceDialog({
  orderId,
  customerId,
  service,
  open,
  onOpenChange,
}: OrderServiceDialogProps) {
  const { t } = useTranslation()
  const create = useCreateOrderService(orderId)
  const update = useUpdateOrderService(orderId)
  const services = useServiceOptions()

  const [values, setValues] = useState<Record<string, string> | null>(null)
  const [serviceId, setServiceId] = useState<string | null>(null)
  const [addressId, setAddressId] = useState<string | null>(null)
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [formError, setFormError] = useState<string | null>(null)

  const current = values ?? serviceFormValues(service)
  const currentServiceId = serviceId ?? service?.serviceId ?? ''
  const currentAddressId = addressId ?? service?.addressId ?? ''

  const patch = (field: string, value: string) =>
    setValues({ ...current, [field]: value })

  const close = () => {
    setValues(null)
    setServiceId(null)
    setAddressId(null)
    setErrors({})
    setFormError(null)
    onOpenChange(false)
  }

  const onError = (cause: unknown) => {
    if (cause instanceof ApiError && cause.isValidation) {
      setErrors(fieldErrorsOf(cause))
      return
    }

    setFormError(cause instanceof ApiError ? cause.message : t('errors.unexpected'))
  }

  const submit = () => {
    setErrors({})
    setFormError(null)

    const payload: OrderServiceInput = { serviceId: currentServiceId, addressId: currentAddressId }

    for (const spec of [...SERVICE_FIELDS, ...SERVICE_PRICE_FIELDS]) {
      const raw = (current[spec.name] ?? '').trim()

      if (raw === '') {
        if (spec.nullable) Object.assign(payload, { [spec.name]: null })
        continue
      }

      Object.assign(payload, { [spec.name]: spec.numeric ? Number(raw) : raw })
    }

    payload.instructions = current.instructions.trim() === '' ? null : current.instructions.trim()

    if (service) update.mutate({ id: service.id, ...payload }, { onSuccess: close, onError })
    else create.mutate(payload, { onSuccess: close, onError })
  }

  const field = (spec: ServiceFieldSpec) => (
    <ControlledField
      key={spec.name}
      label={t(spec.labelKey)}
      type={spec.type}
      min={spec.type === 'number' ? '0' : undefined}
      step={spec.type === 'number' ? '0.001' : undefined}
      value={current[spec.name] ?? ''}
      onChange={(value) => patch(spec.name, value)}
      error={errors[spec.name]}
    />
  )

  return (
    <Dialog open={open} onOpenChange={(next) => (next ? onOpenChange(true) : close())}>
      <DialogContent className="max-h-[85vh] max-w-3xl overflow-y-auto">
        <DialogHeader>
          <DialogTitle>
            {service ? t('orders.services.edit') : t('orders.services.add')}
          </DialogTitle>
          <DialogDescription>{t('orders.services.description')}</DialogDescription>
        </DialogHeader>

        {formError !== null ? (
          <Alert variant="destructive">
            <AlertDescription>{formError}</AlertDescription>
          </Alert>
        ) : null}

        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <AsyncSelect
            label={t('orders.services.service')}
            value={currentServiceId}
            onChange={setServiceId}
            options={services.options}
            isLoading={services.isLoading}
            required
            error={errors.serviceId}
          />

          <AddressPicker
            customerId={customerId}
            value={currentAddressId}
            onChange={setAddressId}
            required
            error={errors.addressId}
          />

          {SERVICE_FIELDS.map(field)}
        </div>

        <fieldset className="border-t pt-4">
          <legend className="mb-1 text-sm font-medium">{t('orders.services.pricing')}</legend>
          <p className="mb-3 text-xs text-muted-foreground">{t('orders.services.pricingHint')}</p>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {SERVICE_PRICE_FIELDS.map(field)}
          </div>
        </fieldset>

        <ControlledField
          label={t('orders.fields.instructions')}
          value={current.instructions ?? ''}
          onChange={(value) => patch('instructions', value)}
          multiline
          error={errors.instructions}
        />

        <DialogFooter>
          <Button type="button" variant="ghost" onClick={close}>
            {t('common.cancel')}
          </Button>
          <Button type="button" onClick={submit} disabled={create.isPending || update.isPending}>
            {t('common.save')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
