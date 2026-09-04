import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router-dom'

import { OrganizationForm } from '../components/OrganizationForm'
import { useCreateOrganization } from '../hooks/useOrganizations'
import { toOrganizationPayload } from '../schemas/organizationSchema'
import { PageHeader } from '@/shared/components/layout/PageHeader'

export function OrganizationCreatePage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const create = useCreateOrganization()

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={t('organizations.create')}
        description={t('organizations.createHint')}
      />

      <OrganizationForm
        submitLabel={t('common.create')}
        onCancel={() => void navigate('/organizations')}
        onSubmit={async (values) => {
          const organization = await create.mutateAsync(toOrganizationPayload(values))
          void navigate(`/organizations/${organization.id}`)
        }}
      />
    </div>
  )
}
