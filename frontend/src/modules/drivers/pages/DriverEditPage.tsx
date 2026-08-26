import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router-dom'

import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { PageHeader } from '@/shared/components/layout/PageHeader'

import { DriverEditForm } from '../components/DriverEditForm'
import { useDriver, useUpdateDriver } from '../hooks/useDrivers'

export function DriverEditPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { id = '' } = useParams()

  const driver = useDriver(id)
  const update = useUpdateDriver()

  if (driver.error) {
    return <ErrorState error={driver.error} onRetry={() => void driver.refetch()} />
  }

  if (driver.data === undefined) {
    return <p className="text-sm text-muted-foreground">{t('common.loading')}</p>
  }

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={t('drivers.editTitle')} description={driver.data.name} />

      <DriverEditForm
        driver={driver.data}
        isPending={update.isPending}
        onCancel={() => void navigate(`/drivers/${id}`)}
        onSubmit={async (payload) => {
          await update.mutateAsync({ id, ...payload })
          void navigate(`/drivers/${id}`)
        }}
      />
    </div>
  )
}
