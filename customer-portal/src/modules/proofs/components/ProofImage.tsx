import { ImageOff } from 'lucide-react'

import { useOrderDocumentFile } from '@/modules/orders/documents/useOrderDocumentFile'

import type { ProofFile } from '../types'

/**
 * Une signature ou une photo de preuve, chargée par la route sécurisée des
 * documents (jeton compris) — jamais par un chemin de stockage. Absente ou
 * illisible : un emplacement neutre, sans erreur.
 */
export function ProofImage({ file, label }: { file: ProofFile | null; label: string }) {
  const image = file !== null && file.mimeType.startsWith('image/')
  const content = useOrderDocumentFile(undefined, file?.id ?? '', image)

  return (
    <figure className="flex flex-col gap-1">
      <figcaption className="text-muted-foreground text-xs font-medium tracking-wide uppercase">{label}</figcaption>
      {content.url ? (
        <img src={content.url} alt={label} className="bg-muted h-32 w-full rounded-md border object-contain" />
      ) : (
        <div className="bg-muted/40 text-muted-foreground flex h-32 flex-col items-center justify-center gap-1 rounded-md border text-sm">
          <ImageOff className="size-5" aria-hidden />
          {file === null ? `Aucune ${label.toLowerCase()}` : !image || content.isError ? 'Aperçu indisponible' : 'Chargement…'}
        </div>
      )}
    </figure>
  )
}
