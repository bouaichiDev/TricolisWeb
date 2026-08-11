import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router-dom'

import { AgencyForm } from '../components/AgencyForm'
import { useAgency, useUpdateAgency } from '../hooks/useAgencies'
import { toAgencyPayload } from '../schemas/agencySchema'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { DetailSkeleton } from '@/shared/components/feedback/LoadingSkeleton'
import { PageHeader } from '@/shared/components/layout/PageHeader'

export function AgencyEditPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { id } = useParams<{ id: string }>()

  const { data: agency, isPending, error, refetch } = useAgency(id)
  const update = useUpdateAgency(id ?? '')

  if (isPending) return <DetailSkeleton rows={4} />
  if (error) return <ErrorState error={error} onRetry={() => void refetch()} />
  if (!agency) return null

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={t('agencies.edit')} description={agency.name} />

      <AgencyForm
        lockCode
        submitLabel={t('common.save')}
        onCancel={() => void navigate(`/agencies/${agency.id}`)}
        defaultValues={{
          code: agency.code,
          name: agency.name,
          shortName: agency.shortName ?? '',
          email: agency.email ?? '',
          phone: agency.phone ?? '',
          color: agency.color ?? '',
          loadingPoint: agency.loadingPoint ?? '',
          status: agency.status,
        }}
        onSubmit={async (values) => {
          const { code: _code, ...rest } = toAgencyPayload(values)
          await update.mutateAsync(rest)
          void navigate(`/agencies/${agency.id}`)
        }}
      />
    </div>
  )
}
