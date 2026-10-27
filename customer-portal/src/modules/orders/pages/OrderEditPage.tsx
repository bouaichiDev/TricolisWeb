import { useQueryClient } from '@tanstack/react-query'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { toast } from 'sonner'

import { usePermissions } from '@/app/auth/useAuth'
import { paths } from '@/app/router/paths'
import { ApiError, errorMessage } from '@/shared/api/errors'
import { ErrorState, InlineLoader } from '@/shared/components/feedback/States'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { Alert, AlertDescription } from '@/shared/components/ui/alert'
import { Button } from '@/shared/components/ui/button'

import { ordersApi } from '../api/ordersApi'
import { uploadDocuments } from '../api/uploadDocuments'
import { fromDetail } from '../form/orderMapping'
import { ordersKeys, useFormOptions, useOrder, useOrderMutation } from '../hooks/useOrders'
import { OrderWizard } from '../wizard/OrderWizard'

/**
 * Modification d'une commande, seulement si le backend l'autorise
 * (`actions.canEdit`). La version lue (`updatedAt`) accompagne l'envoi : si
 * la commande a changé entre-temps, le backend répond un conflit.
 */
export function OrderEditPage() {
  const { id = '' } = useParams()
  const navigate = useNavigate()
  const order = useOrder(id)
  const options = useFormOptions()
  const { can } = usePermissions()
  const queryClient = useQueryClient()
  const update = useOrderMutation((payload: Parameters<typeof ordersApi.update>[1]) => ordersApi.update(id, payload))

  if (order.isPending || options.isPending) return <InlineLoader />
  if (order.isError) return <ErrorState message={errorMessage(order.error)} />
  if (options.isError) return <ErrorState message={errorMessage(options.error)} />

  if (!order.data.actions.canEdit) {
    return (
      <Alert role="alert">
        <AlertDescription className="flex flex-wrap items-center justify-between gap-2">
          Cette commande ne peut plus être modifiée depuis le portail.
          <Button asChild variant="outline" size="sm">
            <Link to={paths.order(id)}>Retour à la commande</Link>
          </Button>
        </AlertDescription>
      </Alert>
    )
  }

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={`Modifier ${order.data.reference}`} description="Le contenu est réécrit d’un bloc à l’enregistrement." />
      <OrderWizard
        initial={fromDetail(order.data)}
        options={options.data}
        submitLabel="Enregistrer les modifications"
        allowDocuments={can('customer.orders.documents.upload')}
        onSubmit={async (payload, documents) => {
          try {
            await update.mutateAsync({ ...payload, updatedAt: order.data.updatedAt ?? '' })
          } catch (caught) {
            if (caught instanceof ApiError && caught.code === 'ORDER_VERSION_CONFLICT') {
              toast.error(caught.message)
              void order.refetch()
            }
            throw caught
          }
          const failed = await uploadDocuments(id, documents)
          await queryClient.invalidateQueries({ queryKey: ordersKeys.documents(id) })

          toast.success('Commande modifiée.')
          if (failed.length > 0) toast.warning(`Documents non joints : ${failed.join(', ')}.`)
          navigate(paths.order(id), { replace: true })
        }}
      />
    </div>
  )
}
