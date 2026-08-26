import { Upload } from 'lucide-react'
import { useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'

import { ControlledField } from '@/shared/components/form/ControlledField'
import { Button } from '@/shared/components/ui/button'
import { Input } from '@/shared/components/ui/input'
import { Label } from '@/shared/components/ui/label'

import { useUploadEntityDocument } from '../hooks/useEntityDocuments'

interface DocumentUploadFormProps {
  entityType: string
  entityId: string
}

/**
 * Dépôt d'un document rattaché à une entité.
 *
 * Le type et le statut sont saisis : ce sont des chaînes libres en base, sans
 * énumération côté serveur. Proposer une liste fermée inventerait un
 * vocabulaire que rien ne valide.
 */
export function DocumentUploadForm({ entityType, entityId }: DocumentUploadFormProps) {
  const { t } = useTranslation()
  const input = useRef<HTMLInputElement>(null)

  const [file, setFile] = useState<File | null>(null)
  const [documentType, setDocumentType] = useState('')

  const upload = useUploadEntityDocument(entityType, entityId)

  const submit = () => {
    if (file === null || documentType.trim() === '') return

    upload.mutate(
      { file, documentType: documentType.trim(), status: 'active' },
      {
        onSuccess: () => {
          setFile(null)
          setDocumentType('')
          // Sans cela, redeposer le meme fichier ne declencherait rien : la
          // valeur de l'input n'aurait pas change.
          if (input.current) input.current.value = ''
        },
      },
    )
  }

  const id = `document-file-${entityType}`

  return (
    <div className="grid items-end gap-4 sm:grid-cols-3">
      <div className="flex flex-col gap-2">
        <Label htmlFor={id}>{t('documents.fields.fileName')}</Label>
        <Input
          id={id}
          ref={input}
          type="file"
          onChange={(event) => setFile(event.target.files?.[0] ?? null)}
        />
      </div>

      <ControlledField
        label={t('documents.fields.documentType')}
        value={documentType}
        onChange={setDocumentType}
        required
      />

      <Button
        type="button"
        onClick={submit}
        disabled={file === null || documentType.trim() === '' || upload.isPending}
      >
        <Upload className="size-4" aria-hidden />
        {upload.isPending ? t('documents.uploading') : t('documents.upload')}
      </Button>
    </div>
  )
}
