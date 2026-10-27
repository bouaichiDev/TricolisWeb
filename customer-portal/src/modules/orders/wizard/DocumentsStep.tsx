import { FileText, Paperclip, Trash2 } from 'lucide-react'
import { useRef, useState } from 'react'

import { EmptyState } from '@/shared/components/feedback/States'
import { Button } from '@/shared/components/ui/button'
import { formatSize } from '@/shared/utils/format'

/** Mêmes bornes que `StoreCustomerOrderDocumentRequest` : le serveur reste juge. */
const MAX_BYTES = 10 * 1024 * 1024
const EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt']
export const MAX_DOCUMENTS = 10

function rejection(file: File): string | null {
  const extension = file.name.split('.').pop()?.toLowerCase() ?? ''

  if (!EXTENSIONS.includes(extension)) return `${file.name} : format non accepté.`
  if (file.size > MAX_BYTES) return `${file.name} : 10 Mo au plus.`

  return null
}

interface DocumentsStepProps {
  files: File[]
  onChange: (files: File[]) => void
}

/**
 * Étape « Documents » : bons, photos, fiches techniques à joindre à la
 * commande. Les fichiers restent dans le navigateur jusqu'à la validation, puis
 * sont déposés sur la commande créée.
 */
export function DocumentsStep({ files, onChange }: DocumentsStepProps) {
  const input = useRef<HTMLInputElement>(null)
  const [errors, setErrors] = useState<string[]>([])

  const add = (list: FileList | null) => {
    const picked = Array.from(list ?? [])
    const refused = picked.map(rejection).filter((message): message is string => message !== null)
    const accepted = picked.filter((file) => rejection(file) === null)
    const room = MAX_DOCUMENTS - files.length

    setErrors(accepted.length > room ? [...refused, `${MAX_DOCUMENTS} documents au plus.`] : refused)
    onChange([...files, ...accepted.slice(0, Math.max(room, 0))])
    if (input.current) input.current.value = ''
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <p className="text-muted-foreground text-sm">
          Facultatif. PDF, images, Word, Excel, CSV ou texte ; 10 Mo par fichier, {MAX_DOCUMENTS} fichiers au plus.
        </p>
        <input
          ref={input}
          type="file"
          multiple
          className="hidden"
          aria-label="Documents à joindre"
          accept={EXTENSIONS.map((extension) => `.${extension}`).join(',')}
          onChange={(event) => add(event.target.files)}
        />
        <Button type="button" variant="outline" disabled={files.length >= MAX_DOCUMENTS} onClick={() => input.current?.click()}>
          <Paperclip className="size-4" aria-hidden /> Ajouter des documents
        </Button>
      </div>

      {errors.length > 0 && (
        <ul role="alert" className="text-destructive list-disc pl-5 text-sm">
          {errors.map((message) => (
            <li key={message}>{message}</li>
          ))}
        </ul>
      )}

      {files.length === 0 ? (
        <EmptyState title="Aucun document" icon={FileText}>
          Vous pourrez aussi en ajouter plus tard, depuis la fiche de la commande.
        </EmptyState>
      ) : (
        <ul className="divide-y rounded-lg border">
          {files.map((file, index) => (
            <li key={`${file.name}-${index}`} className="flex items-center justify-between gap-3 px-4 py-2 text-sm">
              <span className="flex min-w-0 items-center gap-2">
                <FileText className="text-muted-foreground size-4 shrink-0" aria-hidden />
                <span className="truncate font-medium">{file.name}</span>
                <span className="text-muted-foreground shrink-0 text-xs">{formatSize(file.size)}</span>
              </span>
              <Button
                type="button"
                variant="ghost"
                size="icon"
                className="size-8"
                aria-label={`Retirer ${file.name}`}
                onClick={() => onChange(files.filter((_, position) => position !== index))}
              >
                <Trash2 className="size-4" aria-hidden />
              </Button>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
