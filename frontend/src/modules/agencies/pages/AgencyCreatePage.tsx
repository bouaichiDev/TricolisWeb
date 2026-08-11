import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router-dom'

import { AgencyForm } from '../components/AgencyForm'
import { useCreateAgency } from '../hooks/useAgencies'
import { toAgencyPayload } from '../schemas/agencySchema'
import { PageHeader } from '@/shared/components/layout/PageHeader'

export function AgencyCreatePage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const create = useCreateAgency()

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={t('agencies.create')} description={t('agencies.subtitle')} />

      <AgencyForm
        submitLabel={t('common.create')}
        onCancel={() => void navigate('/agencies')}
        onSubmit={async (values) => {
          const agency = await create.mutateAsync(toAgencyPayload(values))
          void navigate(`/agencies/${agency.id}`)
        }}
      />
    </div>
  )
}
