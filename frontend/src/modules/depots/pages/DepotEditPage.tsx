import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router-dom'

import { DepotForm } from '../components/DepotForm'
import { useDepot, useUpdateDepot } from '../hooks/useDepots'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { DetailSkeleton } from '@/shared/components/feedback/LoadingSkeleton'
import { PageHeader } from '@/shared/components/layout/PageHeader'

export function DepotEditPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { agencyId = '', depotId = '' } = useParams<{ agencyId: string; depotId: string }>()

  const { data: depot, isPending, error, refetch } = useDepot(agencyId, depotId)
  const update = useUpdateDepot(agencyId, depotId)

  if (isPending) return <DetailSkeleton />
  if (error) return <ErrorState error={error} onRetry={() => void refetch()} />
  if (!depot) return null

  const back = `/agencies/${agencyId}/depots/${depotId}`

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={depot.name} description={t('depots.editSubtitle')} />

      <DepotForm
        lockCode
        defaultValues={{ code: depot.code, name: depot.name, status: depot.status }}
        submitLabel={t('common.save')}
        onCancel={() => void navigate(back)}
        onSubmit={async (values) => {
          await update.mutateAsync({ name: values.name, status: values.status })
          void navigate(back)
        }}
      />
    </div>
  )
}
