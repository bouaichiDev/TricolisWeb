import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router-dom'

import { CustomerForm } from '../components/CustomerForm'
import { useCustomer, useUpdateCustomer } from '../hooks/useCustomers'
import { toCustomerPayload } from '../schemas/customerSchema'
import { DetailSkeleton } from '@/shared/components/feedback/LoadingSkeleton'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { PageHeader } from '@/shared/components/layout/PageHeader'

export function CustomerEditPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { id } = useParams<{ id: string }>()

  const { data: customer, isPending, error, refetch } = useCustomer(id)
  const update = useUpdateCustomer(id ?? '')

  if (isPending) return <DetailSkeleton />
  if (error) return <ErrorState error={error} onRetry={() => void refetch()} />
  if (!customer) return null

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={t('customers.edit')} description={customer.name} />

      <CustomerForm
        lockCode
        submitLabel={t('common.save')}
        onCancel={() => void navigate(`/customers/${customer.id}`)}
        defaultValues={{
          code: customer.code,
          name: customer.name,
          legalName: customer.legalName ?? '',
          email: customer.email ?? '',
          phone: customer.phone ?? '',
          paymentMode: customer.paymentMode ?? '',
          communicationMode: customer.communicationMode ?? '',
          catalogEnabled: customer.catalogEnabled,
          stockEnabled: customer.stockEnabled,
          packageEnabled: customer.packageEnabled,
          appointmentEnabled: customer.appointmentEnabled,
          trackingEnabled: customer.trackingEnabled,
          status: customer.status,
        }}
        onSubmit={async (values) => {
          // `code` est verrouille a l'edition : l'envoyer quand meme ferait
          // echouer la regle d'unicite contre le client lui-meme.
          const { code: _code, ...rest } = toCustomerPayload(values)
          await update.mutateAsync(rest)
          void navigate(`/customers/${customer.id}`)
        }}
      />
    </div>
  )
}
