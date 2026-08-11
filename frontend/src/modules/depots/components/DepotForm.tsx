import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'

import { DEPOT_FORM_DEFAULTS, depotSchema, type DepotFormValues } from '../schemas/depotSchema'
import { FormActions } from '@/shared/components/form/FormActions'
import { FormErrorSummary } from '@/shared/components/form/FormErrorSummary'
import { StatusSelect } from '@/shared/components/form/StatusSelect'
import { TextField } from '@/shared/components/form/TextField'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { useApiFormError } from '@/shared/hooks/useApiForm'

const STATUSES = ['active', 'inactive'] as const

interface DepotFormProps {
  defaultValues?: Partial<DepotFormValues>
  onSubmit: (values: DepotFormValues) => Promise<unknown>
  onCancel: () => void
  submitLabel: string
  lockCode?: boolean
}

export function DepotForm({
  defaultValues,
  onSubmit,
  onCancel,
  submitLabel,
  lockCode = false,
}: DepotFormProps) {
  const { t } = useTranslation()

  const form = useForm<DepotFormValues>({
    resolver: zodResolver(depotSchema),
    defaultValues: { ...DEPOT_FORM_DEFAULTS, ...defaultValues },
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

      <SectionCard title={t('depots.sections.general')}>
        <div className="grid gap-5 sm:grid-cols-2">
          <TextField
            form={form}
            name="code"
            label={t('depots.fields.code')}
            required
            disabled={lockCode}
          />
          <TextField form={form} name="name" label={t('depots.fields.name')} required />
          <StatusSelect
            form={form}
            name="status"
            label={t('depots.fields.status')}
            options={STATUSES}
          />
        </div>
      </SectionCard>

      <FormActions
        onCancel={onCancel}
        submitLabel={submitLabel}
        isSubmitting={form.formState.isSubmitting}
      />
    </form>
  )
}
