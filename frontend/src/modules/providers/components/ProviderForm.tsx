import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'

import { ReferentialStatusSelect } from '@/modules/statuses/components/ReferentialStatusSelect'
import { FormErrorSummary } from '@/shared/components/form/FormErrorSummary'
import { TextField } from '@/shared/components/form/TextField'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Button } from '@/shared/components/ui/button'
import { useApiFormError } from '@/shared/hooks/useApiForm'

import type { Provider, ProviderPayload } from '../types/provider'

/**
 * Longueurs reprises de `StoreProviderRequest`.
 *
 * `status` n'est **pas** une union figée : les codes viennent du référentiel, et
 * en coder la liste ici la ferait diverger dès qu'un administrateur en ajoute
 * un. La validation de fond reste au serveur, qui vérifie l'appartenance au
 * référentiel.
 */
export const providerSchema = z.object({
  code: z.string().min(1, 'validation.required').max(64, 'validation.max'),
  name: z.string().min(1, 'validation.required').max(255, 'validation.max'),
  status: z.string().min(1, 'validation.required'),
})

export type ProviderFormValues = z.infer<typeof providerSchema>

interface ProviderFormProps {
  provider?: Provider
  isPending: boolean
  onSubmit: (payload: ProviderPayload) => Promise<unknown>
  onCancel: () => void
}

export function ProviderForm({ provider, isPending, onSubmit, onCancel }: ProviderFormProps) {
  const { t } = useTranslation()

  const form = useForm<ProviderFormValues>({
    resolver: zodResolver(providerSchema),
    defaultValues: {
      code: provider?.code ?? '',
      name: provider?.name ?? '',
      status: provider?.status ?? 'active',
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

      <SectionCard title={t('providers.identity')}>
        <div className="grid gap-4 sm:grid-cols-2">
          <TextField form={form} name="code" label={t('providers.fields.code')} required />
          <TextField form={form} name="name" label={t('providers.fields.name')} required />
          <ReferentialStatusSelect
            form={form}
            name="status"
            label={t('providers.fields.status')}
            source="provider"
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
