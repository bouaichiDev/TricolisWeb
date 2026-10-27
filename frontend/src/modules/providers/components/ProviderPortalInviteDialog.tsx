import { zodResolver } from '@hookform/resolvers/zod'
import { useEffect } from 'react'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'

import { FormErrorSummary } from '@/shared/components/form/FormErrorSummary'
import { SelectField } from '@/shared/components/form/SelectField'
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

import type { ProviderPortalInvitePayload, ProviderPortalRole } from '../types/providerPortalUser'

const schema = z.object({
  email: z.string().min(1, 'validation.required').email('validation.email').max(255, 'validation.max'),
  firstName: z.string().max(255, 'validation.max'),
  lastName: z.string().max(255, 'validation.max'),
  roleId: z.string().min(1, 'validation.required'),
})

const DEFAULTS: ProviderPortalInvitePayload = { email: '', firstName: '', lastName: '', roleId: '' }

interface Props {
  open: boolean
  onOpenChange: (open: boolean) => void
  roles: ProviderPortalRole[]
  onSubmit: (values: ProviderPortalInvitePayload) => Promise<unknown>
}

/**
 * Inviter un compte au portail du fournisseur. Le rôle proposé par défaut est
 * « Administrateur fournisseur » ; la liste ne contient que des rôles de
 * portée fournisseur, servis par la fiche du fournisseur.
 */
export function ProviderPortalInviteDialog({ open, onOpenChange, roles, onSubmit }: Props) {
  const { t } = useTranslation()
  const form = useForm<ProviderPortalInvitePayload>({ resolver: zodResolver(schema), defaultValues: DEFAULTS })
  const { formError, handleError, clearError } = useApiFormError(form)
  const defaultRoleId = (roles.find((role) => role.code === 'PROVIDER_ADMIN') ?? roles[0])?.id ?? ''

  useEffect(() => {
    if (!form.getValues('roleId') && defaultRoleId) form.setValue('roleId', defaultRoleId)
  }, [defaultRoleId, form, open])

  const submit = form.handleSubmit(async (values) => {
    clearError()
    try {
      await onSubmit(values)
      form.reset({ ...DEFAULTS, roleId: defaultRoleId })
      onOpenChange(false)
    } catch (error) {
      handleError(error)
    }
  })

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{t('providers.portal.inviteTitle')}</DialogTitle>
          <DialogDescription>{t('providers.portal.inviteDescription')}</DialogDescription>
        </DialogHeader>

        <form onSubmit={submit} className="flex flex-col gap-5" noValidate>
          <FormErrorSummary message={formError} />
          <TextField form={form} name="email" type="email" label={t('providers.portal.fields.email')} required />
          <TextField form={form} name="firstName" label={t('providers.portal.fields.firstName')} />
          <TextField form={form} name="lastName" label={t('providers.portal.fields.lastName')} />
          <SelectField
            form={form}
            name="roleId"
            label={t('providers.portal.fields.role')}
            options={roles.map((role) => ({ value: role.id, label: role.name }))}
            required
          />

          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
              {t('common.cancel')}
            </Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {t('providers.portal.sendInvitation')}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
