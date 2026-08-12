import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router-dom'

import { useCustomerSite, useDeleteCustomerSite } from '../hooks/useCustomerSites'
import { EntityAddressesPanel } from '@/modules/addresses/components/EntityAddressesPanel'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { DetailSkeleton } from '@/shared/components/feedback/LoadingSkeleton'
import { DetailField } from '@/shared/components/layout/DetailField'
import { EntityHeader } from '@/shared/components/layout/EntityHeader'
import { SectionCard } from '@/shared/components/layout/SectionCard'

export function CustomerSiteDetailPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { customerId = '', siteId = '' } = useParams<{ customerId: string; siteId: string }>()
  const [confirmDelete, setConfirmDelete] = useState(false)

  const { data: site, isPending, error, refetch } = useCustomerSite(customerId, siteId)
  const remove = useDeleteCustomerSite(customerId)

  if (isPending) return <DetailSkeleton />
  if (error) return <ErrorState error={error} onRetry={() => void refetch()} />
  if (!site) return null

  return (
    <div className="flex flex-col gap-6">
      <EntityHeader
        title={site.name}
        subtitle={site.code}
        status={site.status}
        editTo={`/customers/${customerId}/sites/${siteId}/edit`}
        editPermission="customer_sites.update"
        onDelete={() => setConfirmDelete(true)}
        deletePermission="customer_sites.delete"
      />

      <SectionCard title={t('customerSites.sections.identity')}>
        <dl className="grid gap-x-8 sm:grid-cols-2">
          <DetailField label={t('customerSites.fields.code')}>{site.code}</DetailField>
          <DetailField label={t('customerSites.fields.siteType')}>{site.siteType}</DetailField>
          <DetailField label={t('customerSites.fields.isDefault')}>
            {site.isDefault ? t('common.yes') : t('common.no')}
          </DetailField>
        </dl>
      </SectionCard>

      {/* Un site porte ses adresses, comme un client porte les siennes : une
          adresse de livraison, une adresse de facturation si elle diffère. Le
          site n'en affichait qu'une, en lecture, et rien ne permettait d'en
          ajouter.

          L'adresse désignée par `site.addressId` figure parmi elles : elle est
          rattachée au site depuis sa création. */}
      <SectionCard title={t('addresses.title')} description={t('addresses.siteHint')}>
        <EntityAddressesPanel
          entityType="customer_site"
          entityId={siteId}
          emptyMessage={t('addresses.emptySite')}
        />
      </SectionCard>

      <ConfirmDialog
        open={confirmDelete}
        onOpenChange={setConfirmDelete}
        title={t('confirm.deleteTitle')}
        description={t('confirm.deleteEntity', { name: site.name })}
        confirmLabel={t('common.delete')}
        isPending={remove.isPending}
        onConfirm={() => {
          remove.mutate(siteId, {
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
