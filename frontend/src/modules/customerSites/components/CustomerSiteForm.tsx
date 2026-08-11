import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'

import {
  CUSTOMER_SITE_FORM_DEFAULTS,
  customerSiteSchema,
  type CustomerSiteFormValues,
} from '../schemas/customerSiteSchema'
import { AddressFields } from '@/modules/addresses/components/AddressFields'
import { CheckboxField } from '@/shared/components/form/CheckboxField'
import { FormActions } from '@/shared/components/form/FormActions'
import { FormErrorSummary } from '@/shared/components/form/FormErrorSummary'
import { StatusSelect } from '@/shared/components/form/StatusSelect'
import { TextField } from '@/shared/components/form/TextField'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { useApiFormError } from '@/shared/hooks/useApiForm'

const STATUSES = ['active', 'inactive'] as const

interface CustomerSiteFormProps {
  defaultValues?: Partial<CustomerSiteFormValues>
  onSubmit: (values: CustomerSiteFormValues) => Promise<unknown>
  onCancel: () => void
  submitLabel: string
  lockCode?: boolean
}

export function CustomerSiteForm({
  defaultValues,
  onSubmit,
  onCancel,
  submitLabel,
  lockCode = false,
}: CustomerSiteFormProps) {
  const { t } = useTranslation()

  const form = useForm<CustomerSiteFormValues>({
    resolver: zodResolver(customerSiteSchema),
    defaultValues: { ...CUSTOMER_SITE_FORM_DEFAULTS, ...defaultValues },
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

      <SectionCard title={t('customerSites.sections.identity')}>
        <div className="grid gap-5 sm:grid-cols-2">
          <TextField
            form={form}
            name="code"
            label={t('customerSites.fields.code')}
            required
            disabled={lockCode}
          />
          <TextField
            form={form}
            name="siteName"
            label={t('customerSites.fields.name')}
            required
          />
          <TextField form={form} name="siteType" label={t('customerSites.fields.siteType')} />
          <StatusSelect
            form={form}
            name="status"
            label={t('customerSites.fields.status')}
            options={STATUSES}
          />
          <CheckboxField
            form={form}
            name="isDefault"
            label={t('customerSites.fields.isDefault')}
            description={t('customerSites.defaultHint')}
          />
        </div>
      </SectionCard>

      <SectionCard title={t('addresses.title')} description={t('customerSites.addressHint')}>
        <AddressFields form={form} />
      </SectionCard>

      <FormActions
        onCancel={onCancel}
        submitLabel={submitLabel}
        isSubmitting={form.formState.isSubmitting}
      />
    </form>
  )
}
