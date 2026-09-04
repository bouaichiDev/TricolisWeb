import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'

import { useProviderOptions } from '@/modules/providers/hooks/useProviders'
import { ReferentialStatusSelect } from '@/modules/statuses/components/ReferentialStatusSelect'
import { AsyncSelect } from '@/shared/components/form/AsyncSelect'
import { FormErrorSummary } from '@/shared/components/form/FormErrorSummary'
import { TextField } from '@/shared/components/form/TextField'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Button } from '@/shared/components/ui/button'
import { useApiFormError } from '@/shared/hooks/useApiForm'

import type { DriverPayload } from '../types/driver'

/** Sentinelle du choix « aucun » : Radix refuse une option de valeur vide. */
const NONE = 'none'

/** Contraintes reprises de `StoreDriverRequest`. */
export const driverCreateSchema = z.object({
  providerId: z.string(),
  code: z.string().min(1, 'validation.required').max(64, 'validation.max'),
  firstName: z.string().min(1, 'validation.required').max(255, 'validation.max'),
  lastName: z.string().min(1, 'validation.required').max(255, 'validation.max'),
  email: z.string().min(1, 'validation.required').email('validation.email'),
  phone: z.string().max(32, 'validation.max'),
  status: z.string().min(1, 'validation.required'),
})

export type DriverCreateValues = z.infer<typeof driverCreateSchema>

interface DriverCreateFormProps {
  /** Prérempli quand on arrive depuis la fiche d'un fournisseur. */
  providerId?: string
  isPending: boolean
  onSubmit: (payload: DriverPayload) => Promise<unknown>
  onCancel: () => void
}

/**
 * Création d'un chauffeur, avec son compte.
 *
 * L'identité est demandée ici parce que le serveur crée **aussi** le compte
 * utilisateur, avec le rôle chauffeur : c'est lui qui ouvrira l'application
 * mobile, et c'est par lui qu'on saura plus tard qui a fait quoi. Le nom du
 * chauffeur est composé du prénom et du nom.
 *
 * Le fournisseur est facultatif : un transporteur emploie ses propres
 * chauffeurs.
 */
export function DriverCreateForm({
  providerId,
  isPending,
  onSubmit,
  onCancel,
}: DriverCreateFormProps) {
  const { t } = useTranslation()
  const providers = useProviderOptions()

  const form = useForm<DriverCreateValues>({
    resolver: zodResolver(driverCreateSchema),
    defaultValues: {
      providerId: providerId ?? NONE,
      code: '',
      firstName: '',
      lastName: '',
      email: '',
      phone: '',
      status: 'active',
    },
  })

  const { formError, handleError, clearError } = useApiFormError(form)

  const submit = form.handleSubmit(async (values) => {
    clearError()
    try {
      await onSubmit({
        ...values,
        providerId: values.providerId === NONE ? null : values.providerId,
        phone: values.phone === '' ? null : values.phone,
      })
    } catch (error) {
      handleError(error)
    }
  })

  return (
    <form onSubmit={submit} className="flex flex-col gap-6" noValidate>
      <FormErrorSummary message={formError} />

      <SectionCard title={t('drivers.identity')} description={t('drivers.identityHint')}>
        <div className="grid gap-4 sm:grid-cols-2">
          <TextField form={form} name="firstName" label={t('drivers.fields.firstName')} required />
          <TextField form={form} name="lastName" label={t('drivers.fields.lastName')} required />
          <TextField
            form={form}
            name="email"
            type="email"
            label={t('drivers.fields.email')}
            required
            description={t('drivers.emailHint')}
          />
          <TextField form={form} name="phone" type="tel" label={t('drivers.fields.phone')} />
        </div>
      </SectionCard>

      <SectionCard title={t('drivers.assignment')}>
        <div className="grid gap-4 sm:grid-cols-2">
          <TextField form={form} name="code" label={t('drivers.fields.code')} required />

          {/* « Aucun » vaut le transporteur lui-même : tous les chauffeurs ne
              viennent pas d'un fournisseur. */}
          <AsyncSelect
            label={t('drivers.fields.provider')}
            value={form.watch('providerId')}
            onChange={(value) => form.setValue('providerId', value, { shouldDirty: true })}
            options={[{ value: NONE, label: t('drivers.ownDriver') }, ...providers.options]}
            isLoading={providers.isLoading}
          />

          <ReferentialStatusSelect
            form={form}
            name="status"
            label={t('drivers.fields.status')}
            source="driver"
          />
        </div>
      </SectionCard>

      <div className="flex justify-end gap-2">
        <Button type="button" variant="outline" onClick={onCancel}>
          {t('common.cancel')}
        </Button>
        <Button type="submit" disabled={isPending || form.formState.isSubmitting}>
          {isPending ? t('common.saving') : t('common.save')}
        </Button>
      </div>
    </form>
  )
}
