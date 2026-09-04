import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router-dom'

import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { DetailSkeleton } from '@/shared/components/feedback/LoadingSkeleton'
import { PageHeader } from '@/shared/components/layout/PageHeader'

import { CustomerApiConfigurationForm } from '../components/CustomerApiConfigurationForm'
import {
  useCustomerApiConfiguration,
  useUpdateCustomerApiConfiguration,
} from '../hooks/useCustomerApiConfigurations'
import { toApiConfigurationFormValues } from '../schemas/customerIntegrationSchemas'
import type { CustomerApiConfigurationPayload } from '../types/customerIntegration'

/**
 * Modification d'un accès API.
 *
 * La clé n'est pas concernée : on change ce qu'elle a le droit de faire et d'où
 * elle peut venir, pas sa valeur. Renouveler la clé est une action distincte,
 * depuis la fiche, parce qu'elle casse immédiatement l'intégration existante.
 */
export function CustomerApiConfigurationEditPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { id = '' } = useParams<{ id: string }>()

  const { data: configuration, isPending, error, refetch } = useCustomerApiConfiguration(id)
  const update = useUpdateCustomerApiConfiguration(id)

  if (isPending) return <DetailSkeleton />
  if (error) return <ErrorState error={error} onRetry={() => void refetch()} />
  if (!configuration) return null

  const submit = async ({ customerId: _customerId, ...rest }: CustomerApiConfigurationPayload) => {
    await update.mutateAsync(rest)
    await navigate(`/integrations/api-access/${id}`)
  }

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={t('integrations.api.edit')} description={configuration.name} />

      <CustomerApiConfigurationForm
        defaultValues={toApiConfigurationFormValues(configuration)}
        onSubmit={submit}
        onCancel={() => void navigate(`/integrations/api-access/${id}`)}
        submitLabel={t('common.save')}
        lockCustomer
      />
    </div>
  )
}
