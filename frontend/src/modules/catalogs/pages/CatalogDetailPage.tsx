import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router-dom'

import { CatalogItemTable } from '../components/CatalogItemTable'
import { useCatalog, useDeleteCatalog } from '../hooks/useCatalogs'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { DetailSkeleton } from '@/shared/components/feedback/LoadingSkeleton'
import { DetailField } from '@/shared/components/layout/DetailField'
import { EntityHeader } from '@/shared/components/layout/EntityHeader'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/shared/components/ui/tabs'
import { formatDateTime } from '@/shared/utils/format'

/**
 * Fiche d'un catalogue et de ses articles.
 *
 * Deux onglets seulement : le catalogue n'a que quatre champs propres, tout le
 * reste est dans ses articles. Les articles sont paginés côté serveur — un
 * catalogue client peut en compter des milliers.
 */
export function CatalogDetailPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { customerId = '', catalogId = '' } = useParams<{
    customerId: string
    catalogId: string
  }>()
  const [confirmDelete, setConfirmDelete] = useState(false)

  const { data: catalog, isPending, error, refetch } = useCatalog(customerId, catalogId)
  const remove = useDeleteCatalog(customerId)

  if (isPending) return <DetailSkeleton rows={4} />
  if (error) return <ErrorState error={error} onRetry={() => void refetch()} />
  if (!catalog) return null

  return (
    <div className="flex flex-col gap-6">
      <EntityHeader
        title={catalog.name}
        subtitle={catalog.code}
        status={catalog.status}
        editTo={`/customers/${customerId}/catalogs/${catalogId}/edit`}
        editPermission="catalogs.update"
        onDelete={() => setConfirmDelete(true)}
        deletePermission="catalogs.delete"
      />

      <Tabs defaultValue="information">
        <TabsList>
          <TabsTrigger value="information">{t('catalogs.tabs.information')}</TabsTrigger>
          <TabsTrigger value="items">{t('catalogs.tabs.items')}</TabsTrigger>
        </TabsList>

        <TabsContent value="information" className="mt-6">
          <SectionCard title={t('catalogs.sections.general')}>
            <dl className="grid gap-x-8 sm:grid-cols-2">
              <DetailField label={t('catalogs.fields.description')}>
                {catalog.description}
              </DetailField>
              <DetailField label={t('catalogs.fields.itemCount')}>
                {t('catalogs.items', { count: catalog.itemCount })}
              </DetailField>
              <DetailField label={t('common.createdAt')}>
                {formatDateTime(catalog.createdAt)}
              </DetailField>
              <DetailField label={t('common.updatedAt')}>
                {formatDateTime(catalog.updatedAt)}
              </DetailField>
            </dl>
          </SectionCard>
        </TabsContent>

        <TabsContent value="items" className="mt-6">
          <CatalogItemTable customerId={customerId} catalogId={catalogId} />
        </TabsContent>
      </Tabs>

      <ConfirmDialog
        open={confirmDelete}
        onOpenChange={setConfirmDelete}
        title={t('confirm.deleteTitle')}
        description={t('catalogs.deleteConfirm', { name: catalog.name })}
        confirmLabel={t('common.delete')}
        isPending={remove.isPending}
        onConfirm={() => {
          remove.mutate(catalogId, {
            onSuccess: () => {
              setConfirmDelete(false)
              void navigate(`/customers/${customerId}`)
            },
          })
        }}
      />
    </div>
  )
}
