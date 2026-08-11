import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'

import {
  AGENCY_FORM_DEFAULTS,
  agencySchema,
  type AgencyFormValues,
} from '../schemas/agencySchema'
import { FormActions } from '@/shared/components/form/FormActions'
import { FormErrorSummary } from '@/shared/components/form/FormErrorSummary'
import { StatusSelect } from '@/shared/components/form/StatusSelect'
import { TextField } from '@/shared/components/form/TextField'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { useApiFormError } from '@/shared/hooks/useApiForm'

const STATUSES = ['active', 'inactive'] as const

interface AgencyFormProps {
  defaultValues?: Partial<AgencyFormValues>
  onSubmit: (values: AgencyFormValues) => Promise<unknown>
  onCancel: () => void
  submitLabel: string
  lockCode?: boolean
}

export function AgencyForm({
  defaultValues,
  onSubmit,
  onCancel,
  submitLabel,
  lockCode = false,
}: AgencyFormProps) {
  const { t } = useTranslation()

  const form = useForm<AgencyFormValues>({
    resolver: zodResolver(agencySchema),
    defaultValues: { ...AGENCY_FORM_DEFAULTS, ...defaultValues },
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

      <SectionCard title={t('agencies.sections.general')}>
        <div className="grid gap-5 sm:grid-cols-2">
          <TextField form={form} name="code" label={t('agencies.fields.code')} required disabled={lockCode} />
          <TextField form={form} name="name" label={t('agencies.fields.name')} required />
          <TextField form={form} name="shortName" label={t('agencies.fields.shortName')} />
          <TextField form={form} name="email" label={t('agencies.fields.email')} type="email" />
          <TextField form={form} name="phone" label={t('agencies.fields.phone')} />
          <StatusSelect form={form} name="status" label={t('agencies.fields.status')} options={STATUSES} />
        </div>
      </SectionCard>

      <SectionCard title={t('agencies.sections.operations')}>
        <div className="grid gap-5 sm:grid-cols-2">
          <TextField
            form={form}
            name="loadingPoint"
            label={t('agencies.fields.loadingPoint')}
          />
          <TextField
            form={form}
            name="color"
            label={t('agencies.fields.color')}
            placeholder="#2563EB"
            description={t('agencies.colorHint')}
          />
        </div>
      </SectionCard>

      <FormActions onCancel={onCancel} submitLabel={submitLabel} isSubmitting={form.formState.isSubmitting} />
    </form>
  )
}
