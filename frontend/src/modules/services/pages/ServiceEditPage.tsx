import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router-dom'

import { ServiceForm } from '../components/ServiceForm'
import { useService, useUpdateService } from '../hooks/useServices'
import { toServicePayload } from '../schemas/serviceSchema'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { DetailSkeleton } from '@/shared/components/feedback/LoadingSkeleton'
import { PageHeader } from '@/shared/components/layout/PageHeader'

export function ServiceEditPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { id = '' } = useParams<{ id: string }>()

  const { data: service, isPending, error, refetch } = useService(id)
  const update = useUpdateService(id)

  if (isPending) return <DetailSkeleton rows={4} />
  if (error) return <ErrorState error={error} onRetry={() => void refetch()} />
  if (!service) return null

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={service.name} description={t('services.edit')} />

      {/* `code` reste modifiable : `UpdateServiceRequest` l'accepte. */}
      <ServiceForm
        defaultValues={{
          code: service.code,
          name: service.name,
          unit: service.unit ?? '',
          defaultDurationMinutes: service.defaultDurationMinutes ?? 0,
          billableToCustomer: service.billableToCustomer,
          payableToProvider: service.payableToProvider,
          requiresAddress: service.requiresAddress,
          requiresContact: service.requiresContact,
          status: service.status,
        }}
        submitLabel={t('common.save')}
        onCancel={() => void navigate(`/services/${id}`)}
        onSubmit={async (values) => {
          await update.mutateAsync(toServicePayload(values))
          void navigate(`/services/${id}`)
        }}
      />
    </div>
  )
}
