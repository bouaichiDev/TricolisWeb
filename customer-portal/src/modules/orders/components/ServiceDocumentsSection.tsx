import { useQuery } from '@tanstack/react-query'
import { FileText } from 'lucide-react'

import { errorMessage } from '@/shared/api/errors'
import { EmptyState, ErrorState, InlineLoader } from '@/shared/components/feedback/States'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { formatDateTime } from '@/shared/utils/format'

import { ordersApi } from '../api/ordersApi'
import { DocumentGallery } from '../documents/DocumentGallery'
import { DownloadButton } from '../documents/DocumentParts'

interface ServiceDocumentsSectionProps {
  orderId: string
  serviceId: string
}

/**
 * Documents d'un service (§15-§16). Le bon de livraison, quand le
 * transporteur l'a généré, est mis en avant : type, nom, date, téléchargement.
 * Aucun bon n'est généré ni simulé ici — sans BL, rien ne l'annonce.
 */
export function ServiceDocumentsSection({ orderId, serviceId }: ServiceDocumentsSectionProps) {
  const documents = useQuery({
    queryKey: ['orders', 'service-documents', orderId, serviceId],
    queryFn: () => ordersApi.serviceDocuments(orderId, serviceId),
  })

  const all = documents.data ?? []
  const notes = all.filter((document) => document.isDeliveryNote)
  const others = all.filter((document) => !document.isDeliveryNote)

  return (
    <SectionCard title="Documents du service">
      {documents.isPending ? (
        <InlineLoader />
      ) : documents.isError ? (
        <ErrorState message={errorMessage(documents.error)} onRetry={() => void documents.refetch()} />
      ) : all.length === 0 ? (
        <EmptyState title="Aucun document pour ce service" icon={FileText} />
      ) : (
        <div className="flex flex-col gap-4">
          {notes.length > 0 && (
            <ul className="flex flex-col gap-2" aria-label="Bons de livraison">
              {notes.map((note) => (
                <li key={note.id} className="flex flex-wrap items-center justify-between gap-2 rounded-md border p-3">
                  <div className="min-w-0 text-sm">
                    <p className="font-medium">Bon de livraison {note.referenceNumber ?? ''}</p>
                    <p className="text-muted-foreground truncate text-xs">
                      {note.fileName} · {formatDateTime(note.createdAt)}
                    </p>
                  </div>
                  <DownloadButton document={note} />
                </li>
              ))}
            </ul>
          )}
          {others.length > 0 && <DocumentGallery documents={others} />}
        </div>
      )}
    </SectionCard>
  )
}
