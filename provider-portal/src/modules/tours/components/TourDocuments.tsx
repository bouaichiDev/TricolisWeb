import { useQuery } from '@tanstack/react-query'
import { Download, FileText } from 'lucide-react'

import { downloadDocument } from '@/shared/api/documentsApi'
import { errorMessage } from '@/shared/api/errors'
import { EmptyState, ErrorState, InlineLoader } from '@/shared/components/feedback/States'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Button } from '@/shared/components/ui/button'
import { formatDateTime, formatSize } from '@/shared/utils/format'

import { toursApi } from '../api/toursApi'

/**
 * Les documents d'exécution de la tournée : consignes, bons des services
 * confirmés, preuves. Le serveur ne rend que ceux-là ; le téléchargement
 * passe par le jeton, jamais par une URL de stockage.
 */
export function TourDocuments({ tourId }: { tourId: string }) {
  const documents = useQuery({ queryKey: ['tour', tourId, 'documents'], queryFn: () => toursApi.documents(tourId) })

  if (documents.isPending) return <InlineLoader />
  if (documents.isError) return <ErrorState message={errorMessage(documents.error)} onRetry={() => void documents.refetch()} />

  return (
    <SectionCard title="Documents">
      {documents.data.length === 0 ? (
        <EmptyState title="Aucun document pour cette tournée" icon={FileText} />
      ) : (
        <ul className="divide-y">
          {documents.data.map((document) => (
            <li key={document.id} className="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
              <span>
                <span className="font-medium">{document.fileName}</span>
                <span className="text-muted-foreground">
                  {' '}· {formatDateTime(document.createdAt)}
                  {document.size !== null && ` · ${formatSize(document.size)}`}
                </span>
              </span>
              <Button size="sm" variant="outline" onClick={() => void downloadDocument(document.id, document.fileName)}>
                <Download className="size-4" aria-hidden /> Télécharger
              </Button>
            </li>
          ))}
        </ul>
      )}
    </SectionCard>
  )
}
