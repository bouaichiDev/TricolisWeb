import { useParams } from 'react-router-dom'

import { usePermissions } from '@/app/auth/useAuth'
import { ApiError, errorMessage } from '@/shared/api/errors'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { ErrorState, InlineLoader } from '@/shared/components/feedback/States'
import { EntityHeader } from '@/shared/components/layout/EntityHeader'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/shared/components/ui/tabs'
import { PublicLinkSection } from '@/modules/tracking/components/PublicLinkSection'
import { RelatedClaimsSection } from '@/modules/claims/components/RelatedClaimsSection'
import { ProofsOfDeliverySection } from '@/modules/proofs/components/ProofsOfDeliverySection'
import { OrderStockTab } from '@/modules/stock/components/OrderStockTab'

import { OrderHeaderActions } from '../components/OrderHeaderActions'
import { useOrder } from '../hooks/useOrders'
import { DocumentsTab } from '../tabs/DocumentsTab'
import { GeneralTab } from '../tabs/GeneralTab'
import { HistoryTab } from '../tabs/HistoryTab'
import { LinesTab } from '../tabs/LinesTab'
import { PackagesTab } from '../tabs/PackagesTab'
import { ServicesTab } from '../tabs/ServicesTab'

export function OrderDetailPage() {
  const { id = '' } = useParams()
  const order = useOrder(id)
  const { can } = usePermissions()

  if (order.isPending) return <InlineLoader />

  if (order.isError) {
    const notFound = order.error instanceof ApiError && order.error.isNotFound

    return (
      <ErrorState
        title={notFound ? 'Commande introuvable' : undefined}
        message={notFound ? 'Cette commande n’existe pas pour votre entreprise.' : errorMessage(order.error)}
      />
    )
  }

  const detail = order.data
  const showPublicLink = detail.actions.canTrack && can('customer.tracking.public_link.view')

  return (
    <div className="flex flex-col gap-6">
      <EntityHeader
        title={detail.reference}
        subtitle={detail.customerReference ? `Votre référence : ${detail.customerReference}` : undefined}
        status={<StatusBadge code={detail.status.code} label={detail.status.label} color={detail.status.color} />}
        actions={<OrderHeaderActions order={detail} />}
      />

      <Tabs defaultValue="general">
        <TabsList className="flex-wrap">
          <TabsTrigger value="general">Général</TabsTrigger>
          <TabsTrigger value="services">Services ({detail.services.length})</TabsTrigger>
          <TabsTrigger value="packages">Colis ({detail.packages.length})</TabsTrigger>
          <TabsTrigger value="lines">Articles ({detail.lines.length})</TabsTrigger>
          {can('customer.orders.documents.view') && <TabsTrigger value="documents">Documents</TabsTrigger>}
          {can('customer.orders.history.view') && <TabsTrigger value="history">Historique</TabsTrigger>}
          {can('customer.orders.proofs_of_delivery.view') && <TabsTrigger value="delivery">Livraison</TabsTrigger>}
          {can('customer.claims.view') && <TabsTrigger value="claims">Réclamations</TabsTrigger>}
          {can('customer.stock.reservations.view') && <TabsTrigger value="stock">Stock réservé</TabsTrigger>}
          {showPublicLink && <TabsTrigger value="public-link">Suivi public</TabsTrigger>}
        </TabsList>
        <TabsContent value="general"><GeneralTab order={detail} /></TabsContent>
        <TabsContent value="services"><ServicesTab order={detail} /></TabsContent>
        <TabsContent value="packages"><PackagesTab order={detail} /></TabsContent>
        <TabsContent value="lines"><LinesTab order={detail} /></TabsContent>
        <TabsContent value="documents"><DocumentsTab orderId={detail.id} /></TabsContent>
        <TabsContent value="history"><HistoryTab orderId={detail.id} /></TabsContent>
        {/* Phase 4 : preuves de livraison et réclamations, chargées à l'ouverture de l'onglet. */}
        <TabsContent value="delivery"><ProofsOfDeliverySection orderId={detail.id} /></TabsContent>
        <TabsContent value="claims"><RelatedClaimsSection orderId={detail.id} /></TabsContent>
        {/* Phase 5 : le stock réservé pour cette commande, en lecture seule. */}
        <TabsContent value="stock"><OrderStockTab orderId={detail.id} /></TabsContent>
        {/* Phase 3 : le lien de suivi public, dans son propre onglet. */}
        {showPublicLink && <TabsContent value="public-link"><PublicLinkSection orderId={detail.id} /></TabsContent>}
      </Tabs>
    </div>
  )
}
