import { useQuery } from '@tanstack/react-query'
import { FileCheck2 } from 'lucide-react'

import { errorMessage } from '@/shared/api/errors'
import { EmptyState, ErrorState, InlineLoader } from '@/shared/components/feedback/States'
import { DetailField } from '@/shared/components/layout/DetailField'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { formatDateTime } from '@/shared/utils/format'

import { proofsApi } from '../api/proofsApi'
import { ProofImage } from './ProofImage'

interface ProofsOfDeliverySectionProps {
  orderId: string
  /** Présent : les preuves de ce service seulement. */
  serviceId?: string
}

/**
 * Preuve de livraison (§19), en lecture seule : destinataire, date, remarque,
 * signature et photo. Rien ne s'y crée ni ne s'y modifie depuis le portail.
 */
export function ProofsOfDeliverySection({ orderId, serviceId }: ProofsOfDeliverySectionProps) {
  const proofs = useQuery({
    queryKey: ['proofs-of-delivery', orderId, serviceId ?? 'order'],
    queryFn: () => (serviceId ? proofsApi.byService(orderId, serviceId) : proofsApi.byOrder(orderId)),
  })

  return (
    <SectionCard title="Preuve de livraison">
      {proofs.isPending ? (
        <InlineLoader />
      ) : proofs.isError ? (
        <ErrorState message={errorMessage(proofs.error)} onRetry={() => void proofs.refetch()} />
      ) : proofs.data.length === 0 ? (
        <EmptyState title="Aucune preuve de livraison pour le moment" icon={FileCheck2} />
      ) : (
        <ul className="flex flex-col gap-4">
          {proofs.data.map((proof) => (
            <li key={proof.id} className="flex flex-col gap-3 rounded-lg border p-4" data-testid="proof-of-delivery">
              <dl className="grid gap-x-6 sm:grid-cols-3">
                <DetailField label="Destinataire">{proof.recipientName}</DetailField>
                <DetailField label="Livré le">{formatDateTime(proof.deliveredAt)}</DetailField>
                {!serviceId && <DetailField label="Service">{proof.service ? `${proof.service.serviceNumber} — ${proof.service.name ?? 'Service'}` : null}</DetailField>}
              </dl>
              <dl>
                <DetailField label="Remarque">{proof.remark}</DetailField>
              </dl>
              <div className="grid gap-4 sm:grid-cols-2">
                <ProofImage file={proof.signature} label="Signature" />
                <ProofImage file={proof.photo} label="Photo" />
              </div>
            </li>
          ))}
        </ul>
      )}
    </SectionCard>
  )
}
