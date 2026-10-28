import { useQuery } from '@tanstack/react-query'
import { FileSignature } from 'lucide-react'
import { useState } from 'react'

import { usePermissions } from '@/app/auth/useAuth'
import { downloadDocument } from '@/shared/api/documentsApi'
import { Button } from '@/shared/components/ui/button'
import { formatDateTime } from '@/shared/utils/format'

import { toursApi } from '../api/toursApi'
import type { TourStop } from '../types'
import { ProofDialog } from './ProofDialog'

const REACHED = ['arrived', 'in_progress', 'completed']

/**
 * Les preuves de livraison d'un arrêt atteint, et la saisie d'une nouvelle
 * quand le serveur l'offre (`canRecordProof`).
 */
export function StopProofs({ tourId, stop }: { tourId: string; stop: TourStop }) {
  const { can } = usePermissions()
  const [recording, setRecording] = useState(false)
  const visible = can('provider.pod.view') && REACHED.includes(stop.status)
  const proofs = useQuery({ queryKey: ['tour', tourId, 'proofs', stop.id], queryFn: () => toursApi.proofs(tourId, stop.id), enabled: visible })
  const canRecord = stop.actions?.canRecordProof === true && stop.services.length > 0

  if (!visible && !canRecord) return null

  return (
    <div className="mt-3 flex flex-col gap-2 border-t pt-3 text-sm">
      {(proofs.data ?? []).map((proof) => (
        <div key={proof.id} className="flex flex-wrap items-center justify-between gap-2">
          <span>
            <FileSignature className="mr-1 inline size-4" aria-hidden />
            {proof.serviceNumber} — reçu par <span className="font-medium">{proof.recipientName}</span>
            <span className="text-muted-foreground"> · {formatDateTime(proof.deliveredAt)}</span>
          </span>
          <span className="flex gap-2">
            {proof.signatureDocumentId && (
              <Button size="sm" variant="ghost" onClick={() => void downloadDocument(proof.signatureDocumentId as string, `signature-${proof.serviceNumber ?? proof.id}`)}>
                Signature
              </Button>
            )}
            {proof.photoDocumentId && (
              <Button size="sm" variant="ghost" onClick={() => void downloadDocument(proof.photoDocumentId as string, `photo-${proof.serviceNumber ?? proof.id}`)}>
                Photo
              </Button>
            )}
          </span>
        </div>
      ))}
      {canRecord && (
        <div>
          <Button size="sm" variant="outline" onClick={() => setRecording(true)}>
            Preuve de livraison
          </Button>
          <ProofDialog tourId={tourId} stop={stop} open={recording} onOpenChange={setRecording} />
        </div>
      )}
    </div>
  )
}
