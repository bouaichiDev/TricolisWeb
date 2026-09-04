import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

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
import { formatDateTime } from '@/shared/utils/format'

import { useInvoiceDocument } from '../hooks/useInvoices'

interface InvoiceDocumentDialogProps {
  invoiceId: string | null
  onClose: () => void
}

/**
 * Aperçu du document d'une facture.
 *
 * **Le HTML vient du serveur.** Le §0.20 interdit un second moteur de rendu en
 * JavaScript : il montrerait un document différent de celui que le client
 * recevra, et l'écart ne se verrait qu'après l'envoi.
 *
 * Il s'affiche dans une **iframe cloisonnée**, jamais par
 * `dangerouslySetInnerHTML` : un modèle est du contenu que des utilisateurs
 * écrivent, et un `<script>` glissé dedans s'exécuterait chez tous ceux qui
 * ouvrent l'aperçu, avec leur session.
 *
 * La **portée** est dite en toutes lettres. Sans elle, voyant sa mise en page
 * globale, un utilisateur ne saurait pas si son modèle client a été ignoré ou
 * s'il n'a jamais été créé.
 */
export function InvoiceDocumentDialog({ invoiceId, onClose }: InvoiceDocumentDialogProps) {
  const { t } = useTranslation()
  const query = useInvoiceDocument(invoiceId, invoiceId !== null)
  const document = query.data

  return (
    <Dialog open={invoiceId !== null} onOpenChange={(open) => !open && onClose()}>
      <DialogContent className="max-h-[90vh] max-w-4xl overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{t('billing.document.title')}</DialogTitle>
          <DialogDescription>{t('billing.document.hint')}</DialogDescription>
        </DialogHeader>

        {query.isPending ? <Skeleton className="h-96 w-full" /> : null}

        {query.error !== null ? (
          <Alert variant="destructive">
            <AlertDescription>{t('billing.document.failed')}</AlertDescription>
          </Alert>
        ) : null}

        {document !== undefined ? (
          <div className="flex flex-col gap-3">
            <div className="flex flex-wrap items-center gap-2 text-sm">
              <Badge variant={document.scope === 'customer' ? 'secondary' : 'outline'}>
                {t(`billing.document.scopes.${document.scope}`)}
              </Badge>

              {document.templateName === null ? null : (
                <Link
                  to="/templates?templateType=invoice"
                  className="underline-offset-2 hover:underline"
                >
                  {document.templateName}
                </Link>
              )}

              {document.renderedAt === null ? null : (
                <span className="text-muted-foreground">
                  {t('billing.document.renderedAt', {
                    date: formatDateTime(document.renderedAt),
                  })}
                </span>
              )}
            </div>

            {document.scope === 'fallback' ? (
              <Alert>
                <AlertDescription>{t('billing.document.noTemplate')}</AlertDescription>
              </Alert>
            ) : null}

            {document.isFrozen ? (
              <Alert>
                <AlertDescription>{t('billing.document.frozen')}</AlertDescription>
              </Alert>
            ) : null}

            <iframe
              title={t('billing.document.title')}
              sandbox=""
              srcDoc={document.html}
              className="h-[60vh] w-full rounded border bg-white"
            />
          </div>
        ) : null}

        <DialogFooter>
          <Button type="button" variant="ghost" onClick={onClose}>
            {t('common.close')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
