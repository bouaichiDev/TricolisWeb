import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router-dom'

import { ServiceForm } from '../components/ServiceForm'
import { useCreateService } from '../hooks/useServices'
import { toServicePayload } from '../schemas/serviceSchema'
import { PageHeader } from '@/shared/components/layout/PageHeader'

export function ServiceCreatePage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const create = useCreateService()

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={t('services.create')} description={t('services.subtitle')} />

      <ServiceForm
        submitLabel={t('common.create')}
        onCancel={() => void navigate('/services')}
        onSubmit={async (values) => {
          const service = await create.mutateAsync(toServicePayload(values))
          void navigate(`/services/${service.id}`)
        }}
      />
    </div>
  )
}
