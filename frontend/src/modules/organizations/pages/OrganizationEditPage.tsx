import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router-dom'

import { OrganizationForm } from '../components/OrganizationForm'
import { useOrganization, useUpdateOrganization } from '../hooks/useOrganizations'
import { toOrganizationFormValues, toOrganizationPayload } from '../schemas/organizationSchema'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { DetailSkeleton } from '@/shared/components/feedback/LoadingSkeleton'
import { PageHeader } from '@/shared/components/layout/PageHeader'

export function OrganizationEditPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { id = '' } = useParams<{ id: string }>()

  const { data: organization, isPending, error, refetch } = useOrganization(id)
  const update = useUpdateOrganization(id)

  if (isPending) return <DetailSkeleton />
  if (error) return <ErrorState error={error} onRetry={() => void refetch()} />
  if (!organization) return null

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={organization.name} description={t('organizations.edit')} />

      {/* `code` reste modifiable : `UpdateOrganizationRequest` l'accepte, avec
          une contrainte d'unicité qui ignore l'organisation courante. */}
      <OrganizationForm
        defaultValues={toOrganizationFormValues(organization)}
        submitLabel={t('common.save')}
        onCancel={() => void navigate(`/organizations/${id}`)}
        onSubmit={async (values) => {
          await update.mutateAsync(toOrganizationPayload(values))
          void navigate(`/organizations/${id}`)
        }}
      />
    </div>
  )
}
