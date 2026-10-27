import { ArrowLeft } from 'lucide-react'
import { useState } from 'react'
import { Link, useParams } from 'react-router-dom'

import { paths } from '@/app/router/paths'
import { ApiError, errorMessage } from '@/shared/api/errors'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { EmptyState, ErrorState, InlineLoader } from '@/shared/components/feedback/States'
import { EntityHeader } from '@/shared/components/layout/EntityHeader'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Button } from '@/shared/components/ui/button'
import { formatDate } from '@/shared/utils/format'

import { EtaCard } from '../components/EtaCard'
import { PublicLinkSection } from '../components/PublicLinkSection'
import { ServiceSelector } from '../components/ServiceSelector'
import { ServiceTrackingCard } from '../components/ServiceTrackingCard'
import { TrackingMapSection } from '../components/TrackingMapSection'
import { TrackingProgress } from '../components/TrackingProgress'
import { TrackingTimeline } from '../components/TrackingTimeline'
import { useOrderTracking } from '../hooks/useOrderTracking'

/**
 * Suivi d'une commande (§37) : en-tête, progression, services, carte, ETA,
 * timeline — et le lien public. Choisir un service resserre la carte, l'ETA
 * et la timeline sur lui.
 */
export function OrderTrackingPage() {
  const { id = '' } = useParams()
  const tracking = useOrderTracking(id)
  const [selectedId, setSelectedId] = useState<string | null>(null)

  if (tracking.isPending) return <InlineLoader label="Chargement du suivi…" />

  if (tracking.isError) {
    const notFound = tracking.error instanceof ApiError && tracking.error.isNotFound

    return (
      <ErrorState
        title={notFound ? 'Commande introuvable' : 'Suivi indisponible'}
        message={notFound ? 'Cette commande n’existe pas pour votre entreprise.' : errorMessage(tracking.error)}
        onRetry={notFound ? undefined : () => void tracking.refetch()}
      />
    )
  }

  const data = tracking.data
  const selected = data.services.find((service) => service.id === selectedId) ?? null
  const services = selected ? [selected] : data.services
  const timeline = selected ? data.timeline.filter((item) => item.orderServiceId === selected.id) : data.timeline
  const points = data.map.points.filter((point) => !selected || point.orderServiceId === selected.id)
  const position = selected ? selected.currentPosition : data.map.currentPosition

  return (
    <div className="flex flex-col gap-6">
      <Button asChild variant="ghost" size="sm" className="w-fit">
        <Link to={paths.order(id)}>
          <ArrowLeft className="size-4" aria-hidden /> Retour à la commande
        </Link>
      </Button>

      <EntityHeader
        title={`Suivi ${data.order.reference}`}
        subtitle={[data.order.customerReference && `Votre référence : ${data.order.customerReference}`, data.order.orderDate && `Commande du ${formatDate(data.order.orderDate)}`].filter(Boolean).join(' · ')}
        status={<StatusBadge code={data.order.status.code} label={data.order.status.label} color={data.order.status.color} />}
      />

      {!data.isActive && <p className="text-muted-foreground text-sm">Cette commande est terminée : le suivi n’est plus rafraîchi.</p>}

      <SectionCard title="Progression">
        <TrackingProgress percent={data.progress.percent} label={`Services effectués : ${data.progress.completedServices} / ${data.progress.totalServices}`} />
      </SectionCard>

      {!data.available && (
        <EmptyState title="Tracking indisponible">
          Votre commande n’est pas encore planifiée : son suivi apparaîtra ici dès qu’elle le sera.
        </EmptyState>
      )}

      <ServiceSelector services={data.services} selectedId={selectedId} onSelect={setSelectedId} />

      <div className="grid gap-6 lg:grid-cols-5">
        <div className="flex flex-col gap-4 lg:col-span-3">
          {services.map((service) => (
            <ServiceTrackingCard key={service.id} service={service} />
          ))}
          <SectionCard title="Carte">
            <TrackingMapSection points={points} position={position} />
          </SectionCard>
        </div>
        <div className="flex flex-col gap-4 lg:col-span-2">
          <EtaCard eta={selected ? selected.eta : data.eta} />
          <SectionCard title="Timeline">
            <TrackingTimeline items={timeline} />
          </SectionCard>
        </div>
      </div>

      <PublicLinkSection orderId={id} />
    </div>
  )
}
