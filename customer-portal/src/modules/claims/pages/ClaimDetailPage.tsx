import { Pencil } from 'lucide-react'
import { useState } from 'react'
import { Link, useParams } from 'react-router-dom'

import { paths } from '@/app/router/paths'
import { ApiError, errorMessage } from '@/shared/api/errors'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { ErrorState, InlineLoader } from '@/shared/components/feedback/States'
import { DetailField } from '@/shared/components/layout/DetailField'
import { EntityHeader } from '@/shared/components/layout/EntityHeader'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Button } from '@/shared/components/ui/button'
import { formatDateTime } from '@/shared/utils/format'

import { ClaimDocumentsSection } from '../components/ClaimDocumentsSection'
import { ClaimEditDialog } from '../components/ClaimEditDialog'
import { useClaim } from '../hooks/useClaims'

/**
 * Détail et suivi d'une réclamation (§10). Le traitement interne — cause,
 * décision, coût, responsable — n'est pas communiqué : aucune règle ne le
 * prévoit. Le client voit l'état (statut, clôture) et ce qu'il a écrit.
 */
export function ClaimDetailPage() {
  const { id = '' } = useParams()
  const claim = useClaim(id)
  const [editing, setEditing] = useState(false)

  if (claim.isPending) return <InlineLoader />

  if (claim.isError) {
    const notFound = claim.error instanceof ApiError && claim.error.isNotFound

    return (
      <ErrorState
        title={notFound ? 'Réclamation introuvable' : undefined}
        message={notFound ? 'Cette réclamation n’existe pas pour votre entreprise.' : errorMessage(claim.error)}
      />
    )
  }

  const detail = claim.data

  return (
    <div className="flex flex-col gap-6">
      <EntityHeader
        title={detail.title}
        subtitle={`Réclamation du ${formatDateTime(detail.createdAt)}`}
        status={<StatusBadge code={detail.status.code} label={detail.status.label} color={detail.status.color} />}
        actions={
          detail.actions.canEdit && (
            <Button variant="outline" onClick={() => setEditing(true)}>
              <Pencil className="size-4" aria-hidden /> Modifier
            </Button>
          )
        }
      />

      <SectionCard title="Réclamation">
        <dl className="grid gap-x-6 sm:grid-cols-2">
          <DetailField label="Type">{detail.claimType}</DetailField>
          <DetailField label="Clôturée le">{detail.closedAt ? formatDateTime(detail.closedAt) : 'En cours de traitement'}</DetailField>
          <DetailField label="Commande">
            {detail.order ? <Link to={paths.order(detail.order.id)} className="text-primary hover:underline">{detail.order.reference}</Link> : null}
          </DetailField>
          <DetailField label="Service concerné">
            {detail.order && detail.orderService ? (
              <Link to={paths.orderService(detail.order.id, detail.orderService.id)} className="text-primary hover:underline">
                {detail.orderService.serviceNumber} — {detail.orderService.name ?? 'Service'}
              </Link>
            ) : null}
          </DetailField>
        </dl>
        <dl>
          <DetailField label="Description">
            {detail.description ? <span className="whitespace-pre-line">{detail.description}</span> : null}
          </DetailField>
        </dl>
      </SectionCard>

      <ClaimDocumentsSection claimId={detail.id} canAttach={detail.actions.canAttach} />

      <ClaimEditDialog claim={detail} open={editing} onOpenChange={setEditing} />
    </div>
  )
}
