import { AlertTriangle, Send } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import { useCloseInvoice, useInvoiceClosurePreview } from '../hooks/useInvoices'
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

interface InvoiceCloseDialogProps {
  invoiceId: string
  invoiceNumber: string
  open: boolean
  onOpenChange: (open: boolean) => void
}

/**
 * Clôturer une facture, en sachant ce que cela déclenche.
 *
 * **La clôture est irréversible et elle envoie.** Le §22 fige le document — le
 * client le détiendra — et le §21 fait de la clôture le seul déclencheur des
 * envois. Confirmer sans montrer les destinations reviendrait à faire signer
 * une expédition à l'aveugle : l'aperçu (§52) les annonce avant.
 *
 * Aucune destination n'est un cas normal, pas une erreur : le §28 refuse qu'un
 * client sans intégration devienne infacturable. Le dialogue le dit et laisse
 * clôturer.
 */
export function InvoiceCloseDialog({
  invoiceId,
  invoiceNumber,
  open,
  onOpenChange,
}: InvoiceCloseDialogProps) {
  const { t } = useTranslation()

  const preview = useInvoiceClosurePreview(invoiceId, open)
  const close = useCloseInvoice(invoiceId)

  const destinations = preview.data?.destinations ?? []
  const closable = preview.data?.closable ?? false

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('billing.invoices.close.title', { number: invoiceNumber })}</DialogTitle>
          <DialogDescription>{t('billing.invoices.close.irreversible')}</DialogDescription>
        </DialogHeader>

        <div className="flex flex-col gap-4">
          <p className="text-sm text-muted-foreground">
            {t('billing.invoices.close.lineCount', { count: preview.data?.lineCount ?? 0 })}
          </p>

          {preview.isPending ? (
            <p className="text-sm text-muted-foreground">{t('common.loading')}</p>
          ) : destinations.length === 0 ? (
            <p className="text-sm text-muted-foreground">
              {t('billing.invoices.close.noDestination')}
            </p>
          ) : (
            <div className="flex flex-col gap-2">
              <p className="text-sm font-medium">{t('billing.invoices.close.willSend')}</p>
              <ul className="flex flex-col gap-2">
                {destinations.map((destination) => (
                  <li
                    key={destination.id}
                    className="flex items-center justify-between rounded-md border px-3 py-2 text-sm"
                  >
                    <span className="flex items-center gap-2">
                      <Send className="size-4 text-muted-foreground" aria-hidden />
                      {destination.name}
                    </span>
                    <span className="flex gap-1">
                      <Badge variant="outline">{destination.transport}</Badge>
                      <Badge variant="outline">{destination.format}</Badge>
                    </span>
                  </li>
                ))}
              </ul>
            </div>
          )}

          {!preview.isPending && !closable ? (
            <p className="flex items-start gap-2 text-sm text-destructive">
              <AlertTriangle className="mt-0.5 size-4 shrink-0" aria-hidden />
              {t('billing.invoices.close.notClosable')}
            </p>
          ) : null}
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            {t('common.cancel')}
          </Button>
          <Button
            disabled={!closable || close.isPending}
            onClick={() => close.mutate(undefined, { onSuccess: () => onOpenChange(false) })}
          >
            {t('billing.invoices.close.confirm')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
