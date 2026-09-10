import { Download, Printer } from 'lucide-react'
import { useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { ApiError } from '@/shared/api/errors'
import { Alert, AlertDescription } from '@/shared/components/ui/alert'
import { Badge } from '@/shared/components/ui/badge'
import { Button } from '@/shared/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/shared/components/ui/dialog'
import { Skeleton } from '@/shared/components/ui/skeleton'

import { downloadDocument } from '@/modules/documents/utils/downloadDocument'
import {
  useDeliveryNoteDocument,
  useDeliveryNoteTemplates,
  useGenerateDeliveryNote,
} from '../../hooks/useDeliveryNote'
import type { OrderService } from '../../types/orderDetail'
import { DeliveryNoteTemplatePicker } from './DeliveryNoteTemplatePicker'

interface DeliveryNoteDialogProps {
  orderId: string
  service: OrderService | null
  onClose: () => void
}

/**
 * Génération du bon de livraison d'un service.
 *
 * **La mise en page ne se règle pas ici.** Elle vient du modèle enregistré dans
 * « Modèles » ; cet écran choisit lequel employer, montre ce qu'il donne, et
 * met le résultat sur papier. Y glisser un éditeur aurait fait retoucher tous
 * les BL depuis une commande.
 *
 * L'aperçu est le **HTML du serveur**, affiché dans une iframe cloisonnée. Ni
 * `dangerouslySetInnerHTML` — un modèle est du contenu que des utilisateurs
 * écrivent, et un `<script>` glissé dedans s'exécuterait avec la session de
 * celui qui l'ouvre — ni rendu reconstruit en JavaScript, qui montrerait autre
 * chose que le PDF remis au client.
 *
 * Sans modèle applicable, l'écran ne propose rien à générer : il dit ce qui
 * manque et mène à « Modèles », pour qui a le droit d'y aller.
 */
export function DeliveryNoteDialog({ orderId, service, onClose }: DeliveryNoteDialogProps) {
  const { t } = useTranslation()
  const [templateId, setTemplateId] = useState<string | undefined>(undefined)
  const frame = useRef<HTMLIFrameElement>(null)

  const serviceId = service?.id ?? null
  const options = useDeliveryNoteTemplates(orderId, serviceId)
  const document = useDeliveryNoteDocument(orderId, serviceId, templateId)
  const generate = useGenerateDeliveryNote(orderId, serviceId)

  const templates = options.data?.data ?? []
  const missing = options.isSuccess && templates.length === 0

  return (
    <Dialog open={service !== null} onOpenChange={(open) => !open && onClose()}>
      <DialogContent className="max-h-[90vh] max-w-4xl overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{t('orders.deliveryNote.title')}</DialogTitle>
          <DialogDescription>
            {t('orders.deliveryNote.hint', {
              service: service?.service?.name ?? service?.serviceNumber ?? '',
            })}
          </DialogDescription>
        </DialogHeader>

        {options.isPending ? <Skeleton className="h-96 w-full" /> : null}

        {missing ? (
          <Alert>
            <AlertDescription className="flex flex-wrap items-center gap-2">
              {t('orders.deliveryNote.noTemplate')}
              <PermissionGuard permission="templates.view">
                <Link
                  to="/templates?category=delivery_note"
                  className="font-medium underline underline-offset-2"
                >
                  {t('orders.deliveryNote.openTemplates')}
                </Link>
              </PermissionGuard>
            </AlertDescription>
          </Alert>
        ) : null}

        {templates.length > 0 ? (
          <div className="flex flex-col gap-3">
            <DeliveryNoteTemplatePicker
              templates={templates}
              value={templateId ?? options.data?.meta.defaultTemplateId ?? undefined}
              onChange={setTemplateId}
            />

            {document.error !== null ? (
              <Alert variant="destructive">
                <AlertDescription>
                  {document.error instanceof ApiError
                    ? document.error.message
                    : t('orders.deliveryNote.failed')}
                </AlertDescription>
              </Alert>
            ) : null}

            {document.isPending ? <Skeleton className="h-[55vh] w-full" /> : null}

            {document.data !== undefined ? (
              <>
                <div className="flex flex-wrap items-center gap-2 text-sm">
                  <Badge variant="outline">{document.data.number}</Badge>
                  <Badge variant={document.data.scope === 'customer' ? 'secondary' : 'outline'}>
                    {t(`orders.deliveryNote.scopes.${document.data.scope}`)}
                  </Badge>
                  <span className="text-muted-foreground">{document.data.templateName}</span>
                </div>

                <iframe
                  ref={frame}
                  title={t('orders.deliveryNote.title')}
                  /**
                   * `allow-same-origin allow-modals`, et surtout **pas**
                   * `allow-scripts`.
                   *
                   * Sans `allow-same-origin`, le cadre a une origine opaque et
                   * `contentWindow.print()` est refusé depuis la page : le
                   * bouton « Imprimer » lèverait une erreur de sécurité.
                   * `allow-modals` est ce que Chrome exige pour `print()`.
                   *
                   * Rendre l'origine commune ne rouvre rien ici : c'est
                   * `allow-scripts` qui autorise l'exécution, et il reste
                   * absent. Un `<script>` glissé dans un modèle ne s'exécute
                   * donc pas — ce que le cadre affiche est un document, pas un
                   * programme.
                   */
                  sandbox="allow-same-origin allow-modals"
                  srcDoc={document.data.html}
                  className="h-[55vh] w-full rounded border bg-white"
                />
              </>
            ) : null}
          </div>
        ) : null}

        <DialogFooter>
          <Button type="button" variant="ghost" onClick={onClose}>
            {t('common.close')}
          </Button>

          <Button
            type="button"
            variant="outline"
            disabled={document.data === undefined}
            onClick={() => frame.current?.contentWindow?.print()}
          >
            <Printer className="size-4" aria-hidden />
            {t('orders.deliveryNote.print')}
          </Button>

          <PermissionGuard permission="delivery_notes.generate">
            <Button
              type="button"
              disabled={document.data === undefined || generate.isPending}
              onClick={() =>
                generate.mutate(document.data?.templateId, {
                  onSuccess: (created) => void downloadDocument(created.id, created.fileName),
                })
              }
            >
              <Download className="size-4" aria-hidden />
              {t('orders.deliveryNote.generate')}
            </Button>
          </PermissionGuard>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
