import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router-dom'

import { CustomerForm } from '../components/CustomerForm'
import { useCreateCustomer } from '../hooks/useCustomers'
import { toCustomerPayload } from '../schemas/customerSchema'
import { PageHeader } from '@/shared/components/layout/PageHeader'

export function CustomerCreatePage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const create = useCreateCustomer()

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={t('customers.createTitle')} description={t('customers.createSubtitle')} />

      <CustomerForm
        submitLabel={t('common.create')}
        onCancel={() => void navigate('/customers')}
        onSubmit={async (values) => {
          const customer = await create.mutateAsync(toCustomerPayload(values))
          void navigate(`/customers/${customer.id}`)
        }}
      />
    </div>
  )
}
