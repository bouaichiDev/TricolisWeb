import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router-dom'

import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { DetailSkeleton } from '@/shared/components/feedback/LoadingSkeleton'
import { PageHeader } from '@/shared/components/layout/PageHeader'

import { CustomerImportConfigurationForm } from '../components/CustomerImportConfigurationForm'
import {
  useCustomerImportConfiguration,
  useUpdateCustomerImportConfiguration,
} from '../hooks/useCustomerImportConfigurations'
import { toImportConfigurationFormValues } from '../schemas/customerIntegrationSchemas'
import type { CustomerImportConfigurationPayload } from '../types/customerIntegration'

export function CustomerImportConfigurationEditPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { id = '' } = useParams<{ id: string }>()

  const { data: configuration, isPending, error, refetch } = useCustomerImportConfiguration(id)
  const update = useUpdateCustomerImportConfiguration(id)

  if (isPending) return <DetailSkeleton />
  if (error) return <ErrorState error={error} onRetry={() => void refetch()} />
  if (!configuration) return null

  // Le client est retiré : `UpdateImportConfigurationRequest` ne le connaît pas.
  const submit = async ({ customerId: _customerId, ...rest }: CustomerImportConfigurationPayload) => {
    await update.mutateAsync(rest)
    await navigate(`/integrations/imports/${id}`)
  }

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={t('integrations.imports.edit')} description={configuration.name} />

      <CustomerImportConfigurationForm
        defaultValues={toImportConfigurationFormValues(configuration)}
        onSubmit={submit}
        onCancel={() => void navigate(`/integrations/imports/${id}`)}
        submitLabel={t('common.save')}
        lockCustomer
      />
    </div>
  )
}
