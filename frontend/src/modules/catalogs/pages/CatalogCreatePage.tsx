import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router-dom'

import { CatalogForm } from '../components/CatalogForm'
import { useCreateCatalog } from '../hooks/useCatalogs'
import { toCatalogPayload } from '../schemas/catalogSchema'
import { PageHeader } from '@/shared/components/layout/PageHeader'

export function CatalogCreatePage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { customerId = '' } = useParams<{ customerId: string }>()
  const create = useCreateCatalog(customerId)

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={t('catalogs.create')} description={t('catalogs.subtitle')} />

      <CatalogForm
        submitLabel={t('common.create')}
        onCancel={() => void navigate(`/customers/${customerId}`)}
        onSubmit={async (values) => {
          const catalog = await create.mutateAsync(toCatalogPayload(values))
          void navigate(`/customers/${customerId}/catalogs/${catalog.id}`)
        }}
      />
    </div>
  )
}
