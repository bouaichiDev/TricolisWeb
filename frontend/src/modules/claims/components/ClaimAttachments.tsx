import { Paperclip, Plus } from 'lucide-react'
import { useRef } from 'react'
import { useTranslation } from 'react-i18next'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { DocumentDownloadLink } from '@/modules/documents/components/DocumentDownloadLink'
import { useEntityDocuments, useUploadEntityDocument } from '@/modules/documents/hooks/useEntityDocuments'
import { Button } from '@/shared/components/ui/button'
import { formatBytes } from '@/shared/utils/format'

/** Type porté par les pièces versées à une réclamation. */
export const CLAIM_EVIDENCE_TYPE = 'claim_evidence'

/** Ce que le navigateur propose ; le serveur reste l'autorité sur les mimes. */
const ACCEPTED = 'image/*,audio/*,.pdf'

/**
 * Photos, vocaux et pièces d'une réclamation.
 *
 * Ce sont des `Document` liés à la réclamation — le §53 interdit une entité
 * `ClaimAttachment`, et il n'y en a pas. Le lien passe par `entityType: claim`,
 * que la morph map connaît.
 *
 * **L'audio est accepté** depuis que `StoreDocumentRequest` admet `mp3`, `wav`,
 * `m4a`, `ogg` et `webm` : un client décrit un dommage en trente secondes de
 * vocal là où il n'écrirait pas trois lignes.
 */
export function ClaimAttachments({ claimId }: { claimId: string }) {
  const { t } = useTranslation()
  const input = useRef<HTMLInputElement>(null)

  const documents = useEntityDocuments('claim', claimId)
  const upload = useUploadEntityDocument('claim', claimId)

  const items = documents.data?.data ?? []

  return (
    <div className="flex flex-col gap-2">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
          {t('claims.attachments')}
        </p>

        <PermissionGuard permission="documents.upload">
          <>
            <input
              ref={input}
              type="file"
              accept={ACCEPTED}
              className="hidden"
              onChange={(event) => {
                const file = event.target.files?.[0]
                if (file === undefined) return

                upload.mutate(
                  { file, documentType: CLAIM_EVIDENCE_TYPE, status: 'active' },
                  // Sans cela, choisir deux fois le meme fichier ne declencherait
                  // rien : la valeur de l'input n'aurait pas change.
                  { onSettled: () => input.current && (input.current.value = '') },
                )
              }}
            />
            <Button
              type="button"
              variant="outline"
              size="sm"
              disabled={upload.isPending}
              onClick={() => input.current?.click()}
            >
              <Plus className="size-4" aria-hidden />
              {t('claims.addAttachment')}
            </Button>
          </>
        </PermissionGuard>
      </div>

      <p className="text-xs text-muted-foreground">{t('claims.attachmentsHint')}</p>

      {items.length === 0 ? (
        <p className="text-sm text-muted-foreground">{t('claims.noAttachment')}</p>
      ) : (
        <ul className="flex flex-col gap-1">
          {items.map((item) => (
            <li key={item.id} className="flex items-center gap-2 rounded-md border px-2 py-1.5">
              <Paperclip className="size-4 shrink-0 text-muted-foreground" aria-hidden />
              <span className="min-w-0 flex-1">
                <span className="block truncate text-sm">{item.fileName}</span>
                <span className="block text-xs text-muted-foreground">
                  {item.mimeType} · {formatBytes(item.size)}
                </span>
              </span>

              <DocumentDownloadLink documentId={item.id} fileName={item.fileName} />
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
