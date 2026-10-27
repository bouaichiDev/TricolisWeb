import { useQueryClient } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { toast } from 'sonner'

import { usePermissions } from '@/app/auth/useAuth'
import { paths } from '@/app/router/paths'
import { errorMessage } from '@/shared/api/errors'
import { ErrorState, InlineLoader } from '@/shared/components/feedback/States'
import { PageHeader } from '@/shared/components/layout/PageHeader'

import { ordersApi } from '../api/ordersApi'
import { uploadDocuments } from '../api/uploadDocuments'
import { emptyOrder } from '../form/orderMapping'
import { ordersKeys, useFormOptions, useOrderMutation } from '../hooks/useOrders'
import { OrderWizard } from '../wizard/OrderWizard'

export function OrderCreatePage() {
  const navigate = useNavigate()
  const options = useFormOptions()
  const create = useOrderMutation(ordersApi.create)
  const { can } = usePermissions()
  const queryClient = useQueryClient()

  if (options.isPending) return <InlineLoader />
  if (options.isError) return <ErrorState message={errorMessage(options.error)} onRetry={() => void options.refetch()} />

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title="Nouvelle commande" description="Renseignez les articles, les colis et les services souhaités." />
      <OrderWizard
        initial={emptyOrder()}
        options={options.data}
        submitLabel="Valider la commande"
        allowDocuments={can('customer.orders.documents.upload')}
        onSubmit={async (payload, documents) => {
          const order = await create.mutateAsync(payload)
          const failed = await uploadDocuments(order.id, documents)
          await queryClient.invalidateQueries({ queryKey: ordersKeys.documents(order.id) })

          toast.success(`Commande ${order.reference} créée.`)
          if (failed.length > 0) {
            toast.warning(`Documents non joints : ${failed.join(', ')}. Ajoutez-les depuis l’onglet Documents.`)
          }
          navigate(paths.order(order.id), { replace: true })
        }}
      />
    </div>
  )
}
