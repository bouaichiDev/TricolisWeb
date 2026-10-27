import { MessageSquareWarning } from 'lucide-react'
import { Link } from 'react-router-dom'

import { Can } from '@/app/guards/Can'
import { paths } from '@/app/router/paths'
import { errorMessage } from '@/shared/api/errors'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { EmptyState, ErrorState, InlineLoader } from '@/shared/components/feedback/States'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Button } from '@/shared/components/ui/button'
import { formatDate } from '@/shared/utils/format'

import { useClaims } from '../hooks/useClaims'

interface RelatedClaimsSectionProps {
  orderId: string
  /** Présent : les réclamations de ce service seulement, et « Signaler un problème ». */
  serviceId?: string
}

/**
 * Les réclamations d'une commande — ou d'un de ses services (§21-§22).
 *
 * Le bouton ouvre la création avec `orderId` (et `orderServiceId`) imposés
 * par la page ; le backend revérifie leur cohérence.
 */
export function RelatedClaimsSection({ orderId, serviceId }: RelatedClaimsSectionProps) {
  const claims = useClaims(serviceId ? { orderServiceId: serviceId, perPage: 50 } : { orderId, perPage: 50 })
  const rows = claims.data?.data ?? []

  return (
    <SectionCard
      title="Réclamations"
      actions={
        <Can permission="customer.claims.create">
          <Button asChild variant="outline">
            <Link to={paths.claimFor(orderId, serviceId)}>
              <MessageSquareWarning className="size-4" aria-hidden /> {serviceId ? 'Signaler un problème' : 'Créer une réclamation'}
            </Link>
          </Button>
        </Can>
      }
    >
      {claims.isPending ? (
        <InlineLoader />
      ) : claims.isError ? (
        <ErrorState message={errorMessage(claims.error)} onRetry={() => void claims.refetch()} />
      ) : rows.length === 0 ? (
        <EmptyState title="Aucune réclamation" icon={MessageSquareWarning} />
      ) : (
        <ul className="divide-y" aria-label="Réclamations liées">
          {rows.map((claim) => (
            <li key={claim.id} className="flex flex-wrap items-center justify-between gap-2 py-2">
              <div className="min-w-0">
                <Link to={paths.claim(claim.id)} className="text-primary font-medium hover:underline">
                  {claim.title}
                </Link>
                <p className="text-muted-foreground text-xs">
                  {claim.claimType}
                  {!serviceId && claim.orderService ? ` · ${claim.orderService.serviceNumber} — ${claim.orderService.name ?? 'Service'}` : ''}
                  {` · créée le ${formatDate(claim.createdAt)}`}
                  {claim.closedAt ? ` · clôturée le ${formatDate(claim.closedAt)}` : ''}
                </p>
              </div>
              <StatusBadge code={claim.status.code} label={claim.status.label} color={claim.status.color} />
            </li>
          ))}
        </ul>
      )}
    </SectionCard>
  )
}
