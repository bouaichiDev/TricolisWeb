import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router-dom'

import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { PageHeader } from '@/shared/components/layout/PageHeader'

import { ProviderForm } from '../components/ProviderForm'
import { useProvider, useUpdateProvider } from '../hooks/useProviders'

export function ProviderEditPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { id = '' } = useParams()

  const provider = useProvider(id)
  const update = useUpdateProvider()

  if (provider.error) {
    return <ErrorState error={provider.error} onRetry={() => void provider.refetch()} />
  }

  if (provider.data === undefined) {
    return <p className="text-sm text-muted-foreground">{t('common.loading')}</p>
  }

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={t('providers.editTitle')} description={provider.data.name} />

      <ProviderForm
        provider={provider.data}
        isPending={update.isPending}
        onCancel={() => void navigate(`/providers/${id}`)}
        onSubmit={async (payload) => {
          await update.mutateAsync({ id, ...payload })
          void navigate(`/providers/${id}`)
        }}
      />
    </div>
  )
}
