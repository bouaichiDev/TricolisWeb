import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router-dom'

import { useCustomerSite, useDeleteCustomerSite } from '../hooks/useCustomerSites'
import { AddressCard } from '@/modules/addresses/components/AddressCard'
import { useAddress } from '@/modules/addresses/hooks/useAddresses'
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
  const address = useAddress(site?.addressId)
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

      {/* L'adresse du site porte ses propres contacts : le magasinier d'un
          entrepôt n'est pas le comptable du siège du client. */}
      <SectionCard title={t('addresses.title')} description={t('addresses.siteHint')}>
        {address.data ? <AddressCard address={address.data} /> : null}
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
