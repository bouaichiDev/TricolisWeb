import { FileText, Upload } from 'lucide-react'
import { useRef } from 'react'
import { toast } from 'sonner'

import { Can } from '@/app/guards/Can'
import { errorMessage } from '@/shared/api/errors'
import { EmptyState, ErrorState, InlineLoader } from '@/shared/components/feedback/States'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Button } from '@/shared/components/ui/button'

import { ordersApi } from '../api/ordersApi'
import { DocumentGallery } from '../documents/DocumentGallery'
import { useOrderDocuments, useOrderMutation } from '../hooks/useOrders'

/**
 * Documents de la commande, en liste ou en vignettes. Le backend ne sert que
 * les documents rattachés à **cette** commande du client.
 */
export function DocumentsTab({ orderId }: { orderId: string }) {
  const documents = useOrderDocuments(orderId, true)
  const upload = useOrderMutation((file: File) => ordersApi.uploadDocument(orderId, file))
  const input = useRef<HTMLInputElement>(null)

  const onFile = async (file: File | undefined) => {
    if (!file) return

    try {
      await upload.mutateAsync(file)
      toast.success('Document ajouté.')
    } catch (caught) {
      toast.error(errorMessage(caught))
    } finally {
      if (input.current) input.current.value = ''
    }
  }

  return (
    <SectionCard
      title="Documents"
      description="Pièces rattachées à cette commande."
      actions={
        <Can permission="customer.orders.documents.upload">
          <input ref={input} type="file" className="hidden" aria-label="Fichier à joindre" onChange={(event) => void onFile(event.target.files?.[0])} />
          <Button variant="outline" disabled={upload.isPending} onClick={() => input.current?.click()}>
            <Upload className="size-4" aria-hidden /> Joindre un document
          </Button>
        </Can>
      }
    >
      {documents.isPending && <InlineLoader />}
      {documents.isError && <ErrorState message={errorMessage(documents.error)} onRetry={() => void documents.refetch()} />}
      {documents.data?.length === 0 && <EmptyState title="Aucun document" icon={FileText} />}
      {documents.data && documents.data.length > 0 && <DocumentGallery orderId={orderId} documents={documents.data} />}
    </SectionCard>
  )
}
