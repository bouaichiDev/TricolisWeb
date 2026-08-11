import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router-dom'

import { CustomerSiteForm } from '../components/CustomerSiteForm'
import { useCustomerSite } from '../hooks/useCustomerSites'
import { useUpdateSiteWithAddress } from '../hooks/useCustomerSiteMutations'
import { useAddress } from '@/modules/addresses/hooks/useAddresses'
import { toAddressFormValues } from '@/modules/addresses/schemas/addressSchema'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { DetailSkeleton } from '@/shared/components/feedback/LoadingSkeleton'
import { PageHeader } from '@/shared/components/layout/PageHeader'

export function CustomerSiteEditPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { customerId = '', siteId = '' } = useParams<{ customerId: string; siteId: string }>()

  const site = useCustomerSite(customerId, siteId)
  const address = useAddress(site.data?.addressId)
  const { submit } = useUpdateSiteWithAddress(customerId, siteId, site.data?.addressId ?? '')

  if (site.isPending || address.isPending) return <DetailSkeleton />
  if (site.error) return <ErrorState error={site.error} onRetry={() => void site.refetch()} />
  if (address.error) {
    return <ErrorState error={address.error} onRetry={() => void address.refetch()} />
  }
  if (!site.data || !address.data) return null

  const back = `/customers/${customerId}/sites/${siteId}`

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={site.data.name} description={t('customerSites.edit')} />

      <CustomerSiteForm
        lockCode
        defaultValues={{
          ...toAddressFormValues(address.data),
          code: site.data.code,
          siteName: site.data.name,
          siteType: site.data.siteType ?? '',
          isDefault: site.data.isDefault,
          status: site.data.status,
        }}
        submitLabel={t('common.save')}
        onCancel={() => void navigate(back)}
        onSubmit={async (values) => {
          await submit(values)
          void navigate(back)
        }}
      />
    </div>
  )
}
