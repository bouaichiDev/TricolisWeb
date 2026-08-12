import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'

import { AddressFields } from './AddressFields'
import { ADDRESS_TYPES } from '../types/address'
import { addressSchema, ADDRESS_FORM_DEFAULTS } from '../schemas/addressSchema'
import { CheckboxField } from '@/shared/components/form/CheckboxField'
import { FormErrorSummary } from '@/shared/components/form/FormErrorSummary'
import { StatusSelect } from '@/shared/components/form/StatusSelect'
import { Button } from '@/shared/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/shared/components/ui/dialog'
import { useApiFormError } from '@/shared/hooks/useApiForm'

/** L'adresse et son type de liaison sont saisis ensemble. */
export const entityAddressSchema = addressSchema.extend({
  addressType: z.string().min(1, 'validation.required'),
  isDefault: z.boolean(),
})

export type EntityAddressFormValues = z.infer<typeof entityAddressSchema>

export const ENTITY_ADDRESS_DEFAULTS: EntityAddressFormValues = {
  ...ADDRESS_FORM_DEFAULTS,
  addressType: 'delivery',
  isDefault: false,
}

interface AddressFormDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  defaultValues?: Partial<EntityAddressFormValues>
  onSubmit: (values: EntityAddressFormValues) => Promise<unknown>
  title: string
}

/**
 * Saisie d'une adresse rattachée à une entité.
 *
 * Le type — livraison, facturation — est demandé en premier : c'est lui qui
 * donne son sens à l'adresse pour l'entité consultée, et il appartient à la
 * liaison, pas à l'adresse.
 */
export function AddressFormDialog({
  open,
  onOpenChange,
  defaultValues,
  onSubmit,
  title,
}: AddressFormDialogProps) {
  const { t } = useTranslation()

  const form = useForm<EntityAddressFormValues>({
    resolver: zodResolver(entityAddressSchema),
    defaultValues: { ...ENTITY_ADDRESS_DEFAULTS, ...defaultValues },
  })

  const { formError, handleError, clearError } = useApiFormError(form)

  const submit = form.handleSubmit(async (values) => {
    clearError()
    try {
      await onSubmit(values)
      form.reset(ENTITY_ADDRESS_DEFAULTS)
      onOpenChange(false)
    } catch (error) {
      handleError(error)
    }
  })

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle>{title}</DialogTitle>
          <DialogDescription>{t('addresses.formHint')}</DialogDescription>
        </DialogHeader>

        <form onSubmit={submit} className="flex flex-col gap-5" noValidate>
          <FormErrorSummary message={formError} />

          <div className="grid gap-5 sm:grid-cols-2">
            <StatusSelect
              form={form}
              name="addressType"
              label={t('addresses.fields.addressType')}
              options={ADDRESS_TYPES}
              translationPrefix="addressTypes"
            />
            <CheckboxField
              form={form}
              name="isDefault"
              label={t('addresses.fields.isDefault')}
              description={t('addresses.defaultHint')}
            />
          </div>

          <AddressFields form={form} />

          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
              {t('common.cancel')}
            </Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {form.formState.isSubmitting ? t('common.saving') : t('common.save')}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
