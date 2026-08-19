import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { ApiError } from '@/shared/api/errors'
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

import type { OrderLineInput } from '../../api/orderContent.api'
import { useCreateOrderLine, useUpdateOrderLine } from '../../hooks/useOrderContent'
import type { OrderLine } from '../../types/orderDetail'
import { blank, fieldErrorsOf, num, optional, text } from './formValues'

interface OrderLineDialogProps {
  orderId: string
  /** Ligne à corriger, ou `null` pour en ajouter une. */
  line: OrderLine | null
  open: boolean
  onOpenChange: (open: boolean) => void
}

/**
 * Ajout ou correction d'une ligne d'une commande existante.
 *
 * L'article de catalogue n'est pas modifiable ici : `UpdateOrderLineRequest` ne
 * l'accepte pas — changer l'article reviendrait à changer la ligne. On retire
 * la ligne et on en ajoute une autre.
 */
export function OrderLineDialog({ orderId, line, open, onOpenChange }: OrderLineDialogProps) {
  const { t } = useTranslation()
  const create = useCreateOrderLine(orderId)
  const update = useUpdateOrderLine(orderId)

  const [values, setValues] = useState<Record<string, string>>({})
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [formError, setFormError] = useState<string | null>(null)

  // La ligne change quand on ouvre le dialogue sur une autre : les valeurs
  // affichées repartent d'elle tant que rien n'a été saisi.
  const current = {
    name: text(line?.name),
    articleCode: text(line?.articleCode),
    barcode: text(line?.barcode),
    description: text(line?.description),
    externalReference: text(line?.externalReference),
    quantity: line ? num(line.quantity) : '1',
    weight: num(line?.weight),
    volume: num(line?.volume),
    length: num(line?.length),
    width: num(line?.width),
    height: num(line?.height),
    purchasePrice: num(line?.purchasePrice),
    sellingPrice: num(line?.sellingPrice),
    ...values,
  }

  const patch = (field: string, value: string) =>
    setValues((previous) => ({ ...previous, [field]: value }))

  const close = () => {
    setValues({})
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

    const payload: OrderLineInput = {
      name: blank(current.name),
      articleCode: blank(current.articleCode),
      barcode: blank(current.barcode),
      description: blank(current.description),
      externalReference: blank(current.externalReference),
      quantity: optional(current.quantity),
      weight: optional(current.weight),
      volume: optional(current.volume),
      length: optional(current.length) ?? null,
      width: optional(current.width) ?? null,
      height: optional(current.height) ?? null,
      purchasePrice: optional(current.purchasePrice) ?? null,
      sellingPrice: optional(current.sellingPrice) ?? null,
    }

    if (line) update.mutate({ id: line.id, ...payload }, { onSuccess: close, onError })
    else create.mutate(payload, { onSuccess: close, onError })
  }

  const field = (name: keyof typeof current, label: string, type: 'text' | 'number' = 'text') => (
    <ControlledField
      label={label}
      type={type}
      min={type === 'number' ? '0' : undefined}
      step={type === 'number' ? '0.001' : undefined}
      value={current[name]}
      onChange={(value) => patch(name, value)}
      error={errors[name]}
    />
  )

  return (
    <Dialog open={open} onOpenChange={(next) => (next ? onOpenChange(true) : close())}>
      <DialogContent className="max-w-2xl">
        <DialogHeader>
          <DialogTitle>{line ? t('orders.lines.edit') : t('orders.lines.create')}</DialogTitle>
          <DialogDescription>
            {line?.fromCatalog ? t('orders.lines.fromCatalog', { name: line.name }) : ' '}
          </DialogDescription>
        </DialogHeader>

        {formError !== null ? (
          <Alert variant="destructive">
            <AlertDescription>{formError}</AlertDescription>
          </Alert>
        ) : null}

        <div className="grid gap-4 sm:grid-cols-2">
          {field('name', t('orders.fields.name'))}
          {field('articleCode', t('orders.fields.articleCode'))}
          {field('barcode', t('orders.fields.barcode'))}
          {field('externalReference', t('orders.fields.externalReference'))}
          {field('quantity', t('orders.fields.quantity'), 'number')}
          {field('weight', t('orders.fields.weight'), 'number')}
          {field('volume', t('orders.fields.volume'), 'number')}
        </div>

        <fieldset className="border-t pt-4">
          <legend className="mb-3 text-sm font-medium">{t('orders.lines.dimensions')}</legend>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {field('length', t('orders.fields.length'), 'number')}
            {field('width', t('orders.fields.width'), 'number')}
            {field('height', t('orders.fields.height'), 'number')}
            {field('purchasePrice', t('orders.fields.purchasePrice'), 'number')}
            {field('sellingPrice', t('orders.fields.sellingPrice'), 'number')}
          </div>
        </fieldset>

        <ControlledField
          label={t('orders.fields.description')}
          value={current.description}
          onChange={(value) => patch('description', value)}
          multiline
          error={errors.description}
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
