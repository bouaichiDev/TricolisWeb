import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'

import { useCustomerOptions } from '@/modules/orders/hooks/useOrderScope'
import { AsyncSelect } from '@/shared/components/form/AsyncSelect'
import { CheckboxField } from '@/shared/components/form/CheckboxField'
import { FormActions } from '@/shared/components/form/FormActions'
import { FormErrorSummary } from '@/shared/components/form/FormErrorSummary'
import { TextField } from '@/shared/components/form/TextField'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { useApiFormError } from '@/shared/hooks/useApiForm'

import { AllowedIpEditor } from './AllowedIpEditor'
import { CustomerApiPermissionsEditor } from './CustomerApiPermissionsEditor'
import {
  API_CONFIGURATION_DEFAULTS,
  customerApiConfigurationSchema,
  toApiConfigurationPayload,
  type CustomerApiConfigurationFormValues,
} from '../schemas/customerIntegrationSchemas'
import type { CustomerApiConfigurationPayload } from '../types/customerIntegration'

interface CustomerApiConfigurationFormProps {
  defaultValues?: Partial<CustomerApiConfigurationFormValues>
  onSubmit: (payload: CustomerApiConfigurationPayload) => Promise<unknown>
  onCancel: () => void
  submitLabel: string
  lockCustomer?: boolean
}

/**
 * Accès API d'un client : la clé **avec laquelle il nous appelle**.
 *
 * À ne pas confondre avec une configuration d'export `rest_api`, qui est le
 * sens inverse — nous appelons son système à lui. Le §19 sépare les deux, et
 * elles ne partagent ni table ni écran.
 *
 * **Aucun champ de clé.** Elle est produite par le serveur à la création et
 * n'est montrée qu'une fois ; `apiKeyHash` n'est ni demandé, ni affiché, ni
 * même présent dans la ressource (§20, §30).
 */
export function CustomerApiConfigurationForm({
  defaultValues,
  onSubmit,
  onCancel,
  submitLabel,
  lockCustomer = false,
}: CustomerApiConfigurationFormProps) {
  const { t } = useTranslation()
  const customers = useCustomerOptions('')

  const form = useForm<CustomerApiConfigurationFormValues>({
    resolver: zodResolver(customerApiConfigurationSchema),
    defaultValues: { ...API_CONFIGURATION_DEFAULTS, ...defaultValues },
  })

  const { formError, handleError, clearError } = useApiFormError(form)

  const submit = form.handleSubmit(async (values) => {
    clearError()
    try {
      await onSubmit(toApiConfigurationPayload(values))
    } catch (error) {
      handleError(error)
    }
  })

  return (
    <form onSubmit={submit} className="flex flex-col gap-6" noValidate>
      <FormErrorSummary message={formError} />

      <SectionCard
        title={t('integrations.api.sections.general')}
        description={t('integrations.api.generalHint')}
      >
        <div className="grid gap-5 sm:grid-cols-2">
          <AsyncSelect
            label={t('integrations.fields.customer')}
            value={form.watch('customerId')}
            onChange={(next) =>
              form.setValue('customerId', next, { shouldDirty: true, shouldValidate: true })
            }
            options={customers.options}
            isLoading={customers.isLoading}
            disabled={lockCustomer}
            required
            description={lockCustomer ? t('integrations.customerLocked') : undefined}
            error={form.formState.errors.customerId?.message}
          />

          <TextField
            form={form}
            name="name"
            label={t('integrations.fields.name')}
            required
            description={t('integrations.api.nameHint')}
          />
        </div>

        <div className="mt-4">
          <CheckboxField
            form={form}
            name="isActive"
            label={t('integrations.fields.isActive')}
            description={t('integrations.api.activeHint')}
          />
        </div>
      </SectionCard>

      <SectionCard
        title={t('integrations.api.sections.access')}
        description={t('integrations.api.accessHint')}
      >
        <div className="flex flex-col gap-6">
          <AllowedIpEditor
            value={form.watch('allowedIps')}
            onChange={(next) => form.setValue('allowedIps', next, { shouldDirty: true })}
            error={form.formState.errors.allowedIps?.message}
          />

          <CustomerApiPermissionsEditor
            value={form.watch('permissions')}
            onChange={(next) => form.setValue('permissions', next, { shouldDirty: true })}
            error={form.formState.errors.permissions?.message}
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
