import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'

import { CONTACT_ROLES } from '../types/address'
import type { NewContactPayload } from '../hooks/useEntityAddressMutations'
import { CheckboxField } from '@/shared/components/form/CheckboxField'
import { FormErrorSummary } from '@/shared/components/form/FormErrorSummary'
import { StatusSelect } from '@/shared/components/form/StatusSelect'
import { TextField } from '@/shared/components/form/TextField'
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

const contactSchema = z.object({
  firstName: z.string().min(1, 'validation.required').max(255, 'validation.max'),
  lastName: z.string().min(1, 'validation.required').max(255, 'validation.max'),
  phone: z.string().max(255, 'validation.max'),
  email: z.string().max(255, 'validation.max').email('validation.email').or(z.literal('')),
  contactRole: z.string().min(1, 'validation.required'),
  isPrimary: z.boolean(),
})

type ContactFormValues = z.infer<typeof contactSchema>

const DEFAULTS: ContactFormValues = {
  firstName: '',
  lastName: '',
  phone: '',
  email: '',
  contactRole: 'delivery',
  isPrimary: false,
}

interface AddressContactDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  onSubmit: (payload: NewContactPayload) => Promise<unknown>
  /** Valeurs existantes : le dialogue passe alors en modification. */
  defaultValues?: Partial<ContactFormValues>
}

/**
 * Saisie d'un contact d'adresse, en création comme en modification.
 *
 * À la création, le contact est créé puis rattaché : l'API n'expose pas de
 * création directe sur une adresse, et un contact doit exister dans
 * l'organisation avant d'y être rattaché.
 *
 * Le rôle vaut pour ce lieu — un même contact peut être livraison ici et
 * facturation ailleurs.
 */
export function AddressContactDialog({
  open,
  onOpenChange,
  onSubmit,
  defaultValues,
}: AddressContactDialogProps) {
  const { t } = useTranslation()
  const isEdit = defaultValues !== undefined

  const form = useForm<ContactFormValues>({
    resolver: zodResolver(contactSchema),
    defaultValues: { ...DEFAULTS, ...defaultValues },
  })

  const { formError, handleError, clearError } = useApiFormError(form)

  const submit = form.handleSubmit(async (values) => {
    clearError()
    try {
      await onSubmit({
        firstName: values.firstName.trim(),
        lastName: values.lastName.trim(),
        phone: values.phone.trim() === '' ? null : values.phone.trim(),
        email: values.email.trim() === '' ? null : values.email.trim(),
        contactRole: values.contactRole,
        isPrimary: values.isPrimary,
      })
      form.reset(DEFAULTS)
      onOpenChange(false)
    } catch (error) {
      handleError(error)
    }
  })

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>{isEdit ? t('addresses.editContact') : t('addresses.addContact')}</DialogTitle>
          <DialogDescription>
            {isEdit ? t('addresses.editContactHint') : t('addresses.addContactHint')}
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={submit} className="flex flex-col gap-5" noValidate>
          <FormErrorSummary message={formError} />

          <div className="grid gap-5 sm:grid-cols-2">
            <TextField form={form} name="firstName" label={t('users.fields.firstName')} required />
            <TextField form={form} name="lastName" label={t('users.fields.lastName')} required />
            <TextField form={form} name="phone" label={t('users.fields.phone')} />
            <TextField form={form} name="email" label={t('users.fields.email')} type="email" />
            <StatusSelect
              form={form}
              name="contactRole"
              label={t('addresses.fields.contactRole')}
              options={CONTACT_ROLES}
              translationPrefix="contactRoles"
            />
            <CheckboxField
              form={form}
              name="isPrimary"
              label={t('addresses.primaryContact')}
              description={t('addresses.primaryHint')}
            />
          </div>

          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
              {t('common.cancel')}
            </Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {form.formState.isSubmitting
                ? t('common.saving')
                : isEdit
                  ? t('common.save')
                  : t('common.add')}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
