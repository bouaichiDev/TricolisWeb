import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'

import {
  SERVICE_FORM_DEFAULTS,
  serviceSchema,
  type ServiceFormValues,
} from '../schemas/serviceSchema'
import { SERVICE_STATUSES } from '../types/service'
import { CheckboxField } from '@/shared/components/form/CheckboxField'
import { FormActions } from '@/shared/components/form/FormActions'
import { FormErrorSummary } from '@/shared/components/form/FormErrorSummary'
import { StatusSelect } from '@/shared/components/form/StatusSelect'
import { TextField } from '@/shared/components/form/TextField'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { useApiFormError } from '@/shared/hooks/useApiForm'

interface ServiceFormProps {
  defaultValues?: Partial<ServiceFormValues>
  onSubmit: (values: ServiceFormValues) => Promise<unknown>
  onCancel: () => void
  submitLabel: string
  lockCode?: boolean
}

/**
 * Formulaire du référentiel de services.
 *
 * `requiresAddress` et `requiresContact` ne sont pas décoratifs : ils décident
 * de ce que le formulaire de commande exigera quand ce service y sera ajouté.
 * Leur description le dit, pour que le choix soit fait en connaissance de cause.
 */
export function ServiceForm({
  defaultValues,
  onSubmit,
  onCancel,
  submitLabel,
  lockCode = false,
}: ServiceFormProps) {
  const { t } = useTranslation()

  const form = useForm<ServiceFormValues>({
    resolver: zodResolver(serviceSchema),
    defaultValues: { ...SERVICE_FORM_DEFAULTS, ...defaultValues },
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

      <SectionCard title={t('services.sections.general')}>
        <div className="grid gap-5 sm:grid-cols-2">
          <TextField
            form={form}
            name="code"
            label={t('services.fields.code')}
            required
            disabled={lockCode}
            description={lockCode ? t('services.codeLocked') : undefined}
          />
          <TextField form={form} name="name" label={t('services.fields.name')} required />
          <TextField
            form={form}
            name="unit"
            label={t('services.fields.unit')}
            required
            description={t('services.unitHint')}
          />
          <TextField
            form={form}
            name="defaultDurationMinutes"
            label={t('services.fields.defaultDurationMinutes')}
            type="number"
            required
          />
          <StatusSelect
            form={form}
            name="status"
            label={t('services.fields.status')}
            options={SERVICE_STATUSES}
          />
        </div>
      </SectionCard>

      <SectionCard
        title={t('services.sections.behaviour')}
        description={t('services.behaviourHint')}
      >
        <div className="grid gap-4 sm:grid-cols-2">
          <CheckboxField
            form={form}
            name="billableToCustomer"
            label={t('services.fields.billableToCustomer')}
          />
          <CheckboxField
            form={form}
            name="payableToProvider"
            label={t('services.fields.payableToProvider')}
          />
          <CheckboxField
            form={form}
            name="requiresAddress"
            label={t('services.fields.requiresAddress')}
            description={t('services.requiresAddressHint')}
          />
          <CheckboxField
            form={form}
            name="requiresContact"
            label={t('services.fields.requiresContact')}
            description={t('services.requiresContactHint')}
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
