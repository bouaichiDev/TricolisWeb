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

import type { Driver, DriverPayload } from '../types/driver'

/** Longueurs reprises de `StoreDriverRequest`. */
export const driverSchema = z.object({
  providerId: z.string().min(1, 'validation.required'),
  code: z.string().min(1, 'validation.required').max(64, 'validation.max'),
  name: z.string().min(1, 'validation.required').max(255, 'validation.max'),
  status: z.string().min(1, 'validation.required'),
})

export type DriverFormValues = z.infer<typeof driverSchema>

interface DriverFormProps {
  driver?: Driver
  /** Prérempli à la création depuis la fiche d'un fournisseur. */
  providerId?: string
  isPending: boolean
  onSubmit: (payload: DriverPayload) => Promise<unknown>
  onCancel: () => void
}

/**
 * Saisie d'un chauffeur.
 *
 * La liste des fournisseurs est celle de l'organisation active : le serveur
 * refuserait de toute façon un fournisseur d'une autre organisation, mais le
 * proposer serait une promesse en l'air.
 *
 * Le fournisseur reste modifiable après coup : `UpdateDriverRequest` l'accepte
 * et vérifie que le code du chauffeur reste unique chez le nouveau. Le
 * verrouiller ici retirerait une opération que le serveur sait faire.
 */
export function DriverForm({
  driver,
  providerId,
  isPending,
  onSubmit,
  onCancel,
}: DriverFormProps) {
  const { t } = useTranslation()
  const providers = useProviderOptions()

  const form = useForm<DriverFormValues>({
    resolver: zodResolver(driverSchema),
    defaultValues: {
      providerId: driver?.providerId ?? providerId ?? '',
      code: driver?.code ?? '',
      name: driver?.name ?? '',
      status: driver?.status ?? 'active',
    },
  })

  const { formError, handleError, clearError } = useApiFormError(form)

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

      <SectionCard title={t('drivers.identity')}>
        <div className="grid gap-4 sm:grid-cols-2">
          <AsyncSelect
            label={t('drivers.fields.provider')}
            value={form.watch('providerId')}
            onChange={(value) =>
              form.setValue('providerId', value, { shouldDirty: true, shouldValidate: true })
            }
            options={providers.options}
            isLoading={providers.isLoading}
            required
            error={form.formState.errors.providerId?.message}
          />

          <TextField form={form} name="code" label={t('drivers.fields.code')} required />
          <TextField form={form} name="name" label={t('drivers.fields.name')} required />

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
