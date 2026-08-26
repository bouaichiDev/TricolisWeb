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

import type { Driver, DriverUpdatePayload } from '../types/driver'

const NONE = 'none'

export const driverEditSchema = z.object({
  providerId: z.string(),
  code: z.string().min(1, 'validation.required').max(64, 'validation.max'),
  name: z.string().min(1, 'validation.required').max(255, 'validation.max'),
  status: z.string().min(1, 'validation.required'),
})

export type DriverEditValues = z.infer<typeof driverEditSchema>

interface DriverEditFormProps {
  driver: Driver
  isPending: boolean
  onSubmit: (payload: DriverUpdatePayload) => Promise<unknown>
  onCancel: () => void
}

/**
 * Modification d'un chauffeur.
 *
 * **L'identité du compte ne se change pas ici.** Le nom, l'adresse électronique
 * et le téléphone appartiennent à l'utilisateur : les modifier au passage
 * changerait un identifiant de connexion sans que personne ne l'ait demandé. La
 * fiche mène au compte, où ils se corrigent.
 */
export function DriverEditForm({ driver, isPending, onSubmit, onCancel }: DriverEditFormProps) {
  const { t } = useTranslation()
  const providers = useProviderOptions()

  const form = useForm<DriverEditValues>({
    resolver: zodResolver(driverEditSchema),
    defaultValues: {
      providerId: driver.providerId ?? NONE,
      code: driver.code,
      name: driver.name,
      status: driver.status,
    },
  })

  const { formError, handleError, clearError } = useApiFormError(form)

  const submit = form.handleSubmit(async (values) => {
    clearError()
    try {
      await onSubmit({
        ...values,
        providerId: values.providerId === NONE ? null : values.providerId,
      })
    } catch (error) {
      handleError(error)
    }
  })

  return (
    <form onSubmit={submit} className="flex flex-col gap-6" noValidate>
      <FormErrorSummary message={formError} />

      <SectionCard title={t('drivers.assignment')}>
        <div className="grid gap-4 sm:grid-cols-2">
          <TextField form={form} name="code" label={t('drivers.fields.code')} required />
          <TextField form={form} name="name" label={t('drivers.fields.name')} required />

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
