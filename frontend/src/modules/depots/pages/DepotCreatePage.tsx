import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router-dom'

import { DepotForm } from '../components/DepotForm'
import { useCreateDepot } from '../hooks/useDepots'
import { PageHeader } from '@/shared/components/layout/PageHeader'

export function DepotCreatePage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { agencyId = '' } = useParams<{ agencyId: string }>()
  const create = useCreateDepot(agencyId)

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={t('depots.create')} />

      <DepotForm
        submitLabel={t('common.create')}
        onCancel={() => void navigate(`/agencies/${agencyId}`)}
        onSubmit={async (values) => {
          const depot = await create.mutateAsync(values)
          void navigate(`/agencies/${agencyId}/depots/${depot.id}`)
        }}
      />
    </div>
  )
}
