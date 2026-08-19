import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { useReferentialOptions } from '@/modules/packages/hooks/useReferentials'
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

import type { OrderPackageInput } from '../../api/orderContent.api'
import { useCreateOrderPackage, useUpdateOrderPackage } from '../../hooks/useOrderContent'
import type { OrderPackage } from '../../types/orderDetail'
import { blank, fieldErrorsOf, num, optional, text } from './formValues'
import { assignableParents, packageDisplayName } from './packageParents'

/** Valeur désignant « aucun parent » ; Radix refuse une option vide. */
const NO_PARENT = 'none'

interface OrderPackageDialogProps {
  orderId: string
  pkg: OrderPackage | null
  /** Colis existants, proposés comme parents. */
  packages: OrderPackage[]
  open: boolean
  onOpenChange: (open: boolean) => void
}

/**
 * Ajout ou correction d'un colis d'une commande existante.
 *
 * Un colis ne peut pas devenir son propre parent, ni celui d'un de ses
 * descendants : ces choix sont retirés de la liste plutôt que refusés après
 * envoi.
 */
export function OrderPackageDialog({
  orderId,
  pkg,
  packages,
  open,
  onOpenChange,
}: OrderPackageDialogProps) {
  const { t } = useTranslation()
  const create = useCreateOrderPackage(orderId)
  const update = useUpdateOrderPackage(orderId)
  const types = useReferentialOptions('package-types')
  const groupings = useReferentialOptions('package-grouping-types')

  const [values, setValues] = useState<Record<string, string>>({})
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [formError, setFormError] = useState<string | null>(null)

  const current = {
    parentPackageId: pkg?.parentPackageId ?? NO_PARENT,
    packageTypeId: text(pkg?.packageTypeId),
    groupingTypeId: text(pkg?.groupingTypeId),
    reference: text(pkg?.reference),
    barcode: text(pkg?.barcode),
    description: text(pkg?.description),
    quantity: pkg ? num(pkg.quantity) : '1',
    weight: num(pkg?.weight),
    volume: num(pkg?.volume),
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

  const parentOptions = [
    { value: NO_PARENT, label: t('common.none') },
    ...assignableParents(packages, pkg).map((item) => ({
      value: item.id,
      label: packageDisplayName(item),
    })),
  ]

  const toOptions = (data?: { data: { id: string; code: string; name: string }[] }) =>
    (data?.data ?? []).map((item) => ({ value: item.id, label: item.name, hint: item.code }))

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

    const payload: OrderPackageInput = {
      parentPackageId:
        current.parentPackageId === NO_PARENT ? null : current.parentPackageId,
      packageTypeId: blank(current.packageTypeId),
      groupingTypeId: blank(current.groupingTypeId),
      reference: blank(current.reference),
      barcode: blank(current.barcode),
      description: blank(current.description),
      quantity: optional(current.quantity),
      weight: optional(current.weight),
      volume: optional(current.volume),
    }

    if (pkg) update.mutate({ id: pkg.id, ...payload }, { onSuccess: close, onError })
    else create.mutate(payload, { onSuccess: close, onError })
  }

  const field = (name: keyof typeof current, text: string, type: 'text' | 'number' = 'text') => (
    <ControlledField
      label={text}
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
          <DialogTitle>{pkg ? t('orders.packages.edit') : t('orders.packages.create')}</DialogTitle>
          <DialogDescription>{t('orders.packages.description')}</DialogDescription>
        </DialogHeader>

        {formError !== null ? (
          <Alert variant="destructive">
            <AlertDescription>{formError}</AlertDescription>
          </Alert>
        ) : null}

        <div className="grid gap-4 sm:grid-cols-2">
          <AsyncSelect
            label={t('orders.packages.parent')}
            value={current.parentPackageId}
            onChange={(value) => patch('parentPackageId', value)}
            options={parentOptions}
            error={errors.parentPackageId}
          />

          <AsyncSelect
            label={t('orders.packages.packageType')}
            value={current.packageTypeId}
            onChange={(value) => patch('packageTypeId', value)}
            options={toOptions(types.data)}
            isLoading={types.isPending}
            error={errors.packageTypeId}
          />

          <AsyncSelect
            label={t('orders.packages.groupingType')}
            value={current.groupingTypeId}
            onChange={(value) => patch('groupingTypeId', value)}
            options={toOptions(groupings.data)}
            isLoading={groupings.isPending}
            error={errors.groupingTypeId}
          />

          {field('reference', t('orders.fields.reference'))}
          {field('barcode', t('orders.fields.barcode'))}
          {field('quantity', t('orders.fields.quantity'), 'number')}
          {field('weight', t('orders.fields.weight'), 'number')}
          {field('volume', t('orders.fields.volume'), 'number')}
        </div>

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
