import { ArrowLeft } from 'lucide-react'
import { Link, useParams } from 'react-router-dom'

import { usePermissions } from '@/app/auth/useAuth'
import { paths } from '@/app/router/paths'
import { RelatedClaimsSection } from '@/modules/claims/components/RelatedClaimsSection'
import { ProofsOfDeliverySection } from '@/modules/proofs/components/ProofsOfDeliverySection'
import { ApiError, errorMessage } from '@/shared/api/errors'
import { ErrorState, InlineLoader } from '@/shared/components/feedback/States'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { Button } from '@/shared/components/ui/button'

import { ServiceDocumentsSection } from '../components/ServiceDocumentsSection'
import { ServiceSummaryCard } from '../components/ServiceSummaryCard'
import { useOrder } from '../hooks/useOrders'

/**
 * Un service de commande (§20) : ses informations, puis — chacune selon sa
 * permission et chargée à l'ouverture de la page — documents et BL, preuve de
 * livraison, réclamations. Le service est cherché dans la commande du client :
 * celui d'une autre commande n'y figure pas.
 */
export function OrderServicePage() {
  const { id = '', serviceId = '' } = useParams()
  const order = useOrder(id)
  const { can } = usePermissions()

  if (order.isPending) return <InlineLoader />

  const service = order.data?.services.find((candidate) => candidate.id === serviceId)

  if (order.isError || service === undefined) {
    const notFound = service === undefined || (order.error instanceof ApiError && order.error.isNotFound)

    return (
      <ErrorState
        title={notFound ? 'Service introuvable' : undefined}
        message={notFound ? 'Ce service n’existe pas dans les commandes de votre entreprise.' : errorMessage(order.error)}
      />
    )
  }

  const detail = order.data
  const packageNumber = new Map(detail.packages.map((pkg, index) => [pkg.id, index + 1]))

  return (
    <div className="flex flex-col gap-6">
      <Button asChild variant="ghost" size="sm" className="w-fit">
        <Link to={paths.order(detail.id)}>
          <ArrowLeft className="size-4" aria-hidden /> Retour à la commande {detail.reference}
        </Link>
      </Button>

      <PageHeader title={`${service.serviceNumber} — ${service.service?.name ?? 'Service'}`} description={`Commande ${detail.reference}`} />

      <ServiceSummaryCard service={service} packageNumber={packageNumber} />

      {can('customer.orders.documents.view') && <ServiceDocumentsSection orderId={detail.id} serviceId={service.id} />}
      {can('customer.orders.proofs_of_delivery.view') && <ProofsOfDeliverySection orderId={detail.id} serviceId={service.id} />}
      {can('customer.claims.view') && <RelatedClaimsSection orderId={detail.id} serviceId={service.id} />}
    </div>
  )
}
