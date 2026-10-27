import { Paperclip } from 'lucide-react'
import { useRef } from 'react'
import { toast } from 'sonner'

import { DocumentGallery } from '@/modules/orders/documents/DocumentGallery'
import { errorMessage } from '@/shared/api/errors'
import { EmptyState, ErrorState, InlineLoader } from '@/shared/components/feedback/States'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Button } from '@/shared/components/ui/button'

import { claimsApi } from '../api/claimsApi'
import { useClaimDocuments, useClaimMutation } from '../hooks/useClaims'

interface ClaimDocumentsSectionProps {
  claimId: string
  canAttach: boolean
}

/**
 * Pièces jointes d'une réclamation (§23) : photos, bons, courriers. Même
 * galerie que les documents de commande ; le fichier passe par la route
 * sécurisée `/documents/{id}/download`. Ajout possible tant que la
 * réclamation est ouverte et que le membre peut créer des réclamations.
 */
export function ClaimDocumentsSection({ claimId, canAttach }: ClaimDocumentsSectionProps) {
  const input = useRef<HTMLInputElement>(null)
  const documents = useClaimDocuments(claimId)
  const upload = useClaimMutation(claimsApi.uploadDocument)

  const send = async (file: File | undefined) => {
    if (!file) return

    try {
      await upload.mutateAsync({ id: claimId, file })
      toast.success(`${file.name} ajouté.`)
    } catch (caught) {
      toast.error(errorMessage(caught))
    } finally {
      if (input.current) input.current.value = ''
    }
  }

  return (
    <SectionCard
      title="Pièces jointes"
      description="Photos, bons de livraison annotés, courriers… (PDF, images, Office, 10 Mo au plus)."
      actions={
        canAttach && (
          <>
            <input ref={input} type="file" className="hidden" aria-label="Pièce jointe" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.csv,.txt" onChange={(event) => void send(event.target.files?.[0])} />
            <Button variant="outline" disabled={upload.isPending} onClick={() => input.current?.click()}>
              <Paperclip className="size-4" aria-hidden /> Joindre un fichier
            </Button>
          </>
        )
      }
    >
      {documents.isPending ? (
        <InlineLoader />
      ) : documents.isError ? (
        <ErrorState message={errorMessage(documents.error)} onRetry={() => void documents.refetch()} />
      ) : documents.data.length === 0 ? (
        <EmptyState title="Aucune pièce jointe" icon={Paperclip} />
      ) : (
        <DocumentGallery documents={documents.data} />
      )}
    </SectionCard>
  )
}
