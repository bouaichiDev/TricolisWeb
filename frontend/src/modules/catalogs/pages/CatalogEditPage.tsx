import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router-dom'

import { CatalogForm } from '../components/CatalogForm'
import { useCatalog, useUpdateCatalog } from '../hooks/useCatalogs'
import { toCatalogPayload } from '../schemas/catalogSchema'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { DetailSkeleton } from '@/shared/components/feedback/LoadingSkeleton'
import { PageHeader } from '@/shared/components/layout/PageHeader'

export function CatalogEditPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { customerId = '', catalogId = '' } = useParams<{
    customerId: string
    catalogId: string
  }>()

  const { data: catalog, isPending, error, refetch } = useCatalog(customerId, catalogId)
  const update = useUpdateCatalog(customerId, catalogId)

  if (isPending) return <DetailSkeleton rows={4} />
  if (error) return <ErrorState error={error} onRetry={() => void refetch()} />
  if (!catalog) return null

  const back = `/customers/${customerId}/catalogs/${catalogId}`

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={catalog.name} description={t('catalogs.edit')} />

      {/* `code` reste modifiable : `UpdateCatalogRequest` l'accepte. */}
      <CatalogForm
        defaultValues={{
          code: catalog.code,
          name: catalog.name,
          description: catalog.description ?? '',
          status: catalog.status,
        }}
        submitLabel={t('common.save')}
        onCancel={() => void navigate(back)}
        onSubmit={async (values) => {
          await update.mutateAsync(toCatalogPayload(values))
          void navigate(back)
        }}
      />
    </div>
  )
}
