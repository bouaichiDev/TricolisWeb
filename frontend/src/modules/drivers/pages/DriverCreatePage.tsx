import { useTranslation } from 'react-i18next'
import { useNavigate, useSearchParams } from 'react-router-dom'

import { PageHeader } from '@/shared/components/layout/PageHeader'

import { DriverForm } from '../components/DriverForm'
import { useCreateDriver } from '../hooks/useDrivers'

/**
 * Création d'un chauffeur.
 *
 * `?providerId=` préremplit le fournisseur quand on arrive depuis sa fiche :
 * c'est le §32 du prompt, et cela évite de rechercher dans la liste celui qu'on
 * regardait à l'instant.
 */
export function DriverCreatePage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const [params] = useSearchParams()
  const create = useCreateDriver()

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={t('drivers.createTitle')} description={t('drivers.subtitle')} />

      <DriverForm
        providerId={params.get('providerId') ?? undefined}
        isPending={create.isPending}
        onCancel={() => void navigate('/drivers')}
        onSubmit={async (payload) => {
          const driver = await create.mutateAsync(payload)
          void navigate(`/drivers/${driver.id}`)
        }}
      />
    </div>
  )
}
