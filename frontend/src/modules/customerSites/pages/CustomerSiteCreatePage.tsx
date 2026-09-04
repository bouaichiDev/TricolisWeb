import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router-dom'

import { CustomerSiteForm } from '../components/CustomerSiteForm'
import { useCreateSiteWithAddress } from '../hooks/useCustomerSiteMutations'
import { PageHeader } from '@/shared/components/layout/PageHeader'

export function CustomerSiteCreatePage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { customerId = '' } = useParams<{ customerId: string }>()
  const { submit } = useCreateSiteWithAddress(customerId)

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={t('customerSites.create')} description={t('customerSites.subtitle')} />

      <CustomerSiteForm
        submitLabel={t('common.create')}
        onCancel={() => void navigate(`/customers/${customerId}`)}
        onSubmit={async (values) => {
          const site = await submit(values)
          void navigate(`/customers/${customerId}/sites/${site.id}`)
        }}
      />
    </div>
  )
}
