import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router-dom'

import { PageHeader } from '@/shared/components/layout/PageHeader'

import { ProviderForm } from '../components/ProviderForm'
import { useCreateProvider } from '../hooks/useProviders'

export function ProviderCreatePage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const create = useCreateProvider()

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={t('providers.createTitle')} description={t('providers.subtitle')} />

      <ProviderForm
        isPending={create.isPending}
        onCancel={() => void navigate('/providers')}
        onSubmit={async (payload) => {
          const provider = await create.mutateAsync(payload)
          void navigate(`/providers/${provider.id}`)
        }}
      />
    </div>
  )
}
