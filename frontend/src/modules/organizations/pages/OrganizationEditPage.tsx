import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router-dom'

import { OrganizationForm } from '../components/OrganizationForm'
import { useOrganization, useUpdateOrganization } from '../hooks/useOrganizations'
import { toOrganizationFormValues, toOrganizationPayload } from '../schemas/organizationSchema'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { DetailSkeleton } from '@/shared/components/feedback/LoadingSkeleton'
import { PageHeader } from '@/shared/components/layout/PageHeader'

/**
 * Modification d'une organisation.
 *
 * `organizationId` la réutilise pour « Mon organisation », comme la fiche :
 * l'identifiant vient alors de l'appartenance active, et le retour se fait vers
 * `/my-organization` — dont la route est ouverte à un organisme, là où
 * `/organizations/{id}` est réservée à la plateforme.
 */
export function OrganizationEditPage({ organizationId }: { organizationId?: string } = {}) {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { id = '' } = useParams<{ id: string }>()

  const target = organizationId ?? id
  const back = organizationId === undefined ? `/organizations/${target}` : '/my-organization'

  const { data: organization, isPending, error, refetch } = useOrganization(target)
  const update = useUpdateOrganization(target)

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
        onCancel={() => void navigate(back)}
        onSubmit={async (values) => {
          await update.mutateAsync(toOrganizationPayload(values))
          void navigate(back)
        }}
      />
    </div>
  )
}
