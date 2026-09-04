import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'

import {
  ORGANIZATION_FORM_DEFAULTS,
  organizationSchema,
  type OrganizationFormValues,
} from '../schemas/organizationSchema'
import { ORGANIZATION_STATUSES } from '../types/organization'
import { FormActions } from '@/shared/components/form/FormActions'
import { FormErrorSummary } from '@/shared/components/form/FormErrorSummary'
import { StatusSelect } from '@/shared/components/form/StatusSelect'
import { TextField } from '@/shared/components/form/TextField'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { useApiFormError } from '@/shared/hooks/useApiForm'

interface OrganizationFormProps {
  defaultValues?: Partial<OrganizationFormValues>
  onSubmit: (values: OrganizationFormValues) => Promise<unknown>
  onCancel: () => void
  submitLabel: string
  lockCode?: boolean
}

export function OrganizationForm({
  defaultValues,
  onSubmit,
  onCancel,
  submitLabel,
  lockCode = false,
}: OrganizationFormProps) {
  const { t } = useTranslation()

  const form = useForm<OrganizationFormValues>({
    resolver: zodResolver(organizationSchema),
    defaultValues: { ...ORGANIZATION_FORM_DEFAULTS, ...defaultValues },
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

      <SectionCard title={t('organizations.sections.identity')}>
        <div className="grid gap-5 sm:grid-cols-2">
          <TextField
            form={form}
            name="code"
            label={t('organizations.fields.code')}
            required
            disabled={lockCode}
            description={lockCode ? t('organizations.codeLocked') : undefined}
          />
          <TextField form={form} name="name" label={t('organizations.fields.name')} required />
          <TextField form={form} name="legalName" label={t('organizations.fields.legalName')} />
          <StatusSelect
            form={form}
            name="status"
            label={t('organizations.fields.status')}
            options={ORGANIZATION_STATUSES}
          />
        </div>
      </SectionCard>

      <SectionCard title={t('organizations.sections.legal')}>
        <div className="grid gap-5 sm:grid-cols-2">
          <TextField
            form={form}
            name="registrationNumber"
            label={t('organizations.fields.registrationNumber')}
          />
          <TextField form={form} name="taxNumber" label={t('organizations.fields.taxNumber')} />
          <TextField
            form={form}
            name="email"
            label={t('organizations.fields.email')}
            type="email"
          />
          <TextField form={form} name="phone" label={t('organizations.fields.phone')} />
        </div>
      </SectionCard>

      <SectionCard
        title={t('organizations.sections.preferences')}
        description={t('organizations.preferencesHint')}
      >
        <div className="grid gap-5 sm:grid-cols-3">
          <TextField
            form={form}
            name="preferredLanguage"
            label={t('organizations.fields.preferredLanguage')}
            placeholder="fr"
          />
          <TextField
            form={form}
            name="timezone"
            label={t('organizations.fields.timezone')}
            placeholder="Europe/Paris"
          />
          <TextField
            form={form}
            name="currencyCode"
            label={t('organizations.fields.currencyCode')}
            placeholder="EUR"
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
