import { Paperclip, X } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { Button } from '@/shared/components/ui/button'

import { useCommunicationAttachments, useDetachDocument } from '../hooks/useOrderCommunications'

interface CommunicationAttachmentListProps {
  communicationId: string
  /** Faux dès que la communication est partie : on ne retouche pas l'envoyé. */
  editable: boolean
}

/**
 * Pièces jointes d'une communication.
 *
 * Ce sont des `Document` existants, rattachés par leur identifiant : le §33
 * interdit un système de téléversement parallèle, et il n'y en a pas ici.
 *
 * Les noms affichés sont les **snapshots** pris au rattachement. Renommer le
 * document ensuite ne réécrit pas ce qui est parti.
 */
export function CommunicationAttachmentList({
  communicationId,
  editable,
}: CommunicationAttachmentListProps) {
  const { t } = useTranslation()
  const attachments = useCommunicationAttachments(communicationId)
  const detach = useDetachDocument(communicationId)

  const items = attachments.data ?? []

  return (
    <div className="flex flex-col gap-2">
      <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
        {t('communications.attachments')}
      </p>

      {items.length === 0 ? (
        <p className="text-sm text-muted-foreground">{t('communications.noAttachment')}</p>
      ) : (
        <ul className="flex flex-col gap-1">
          {items.map((item) => (
            <li key={item.id} className="flex items-center gap-2 rounded-md border px-2 py-1.5">
              <Paperclip className="size-4 shrink-0 text-muted-foreground" aria-hidden />
              <span className="min-w-0 flex-1">
                <span className="block truncate text-sm">{item.fileNameSnapshot}</span>
                <span className="block text-xs text-muted-foreground">
                  {item.mimeTypeSnapshot}
                </span>
              </span>

              {editable ? (
                <PermissionGuard permission="communication_attachments.delete">
                  <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    disabled={detach.isPending}
                    onClick={() => detach.mutate(item.id)}
                    title={t('communications.detach')}
                    aria-label={t('communications.detach')}
                  >
                    <X className="size-4" aria-hidden />
                  </Button>
                </PermissionGuard>
              ) : null}
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
