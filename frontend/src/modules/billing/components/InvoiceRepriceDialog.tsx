import { useTranslation } from 'react-i18next'

import { useApplyRepricing, useRepricingPreview } from '../hooks/useInvoices'
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
import { formatMoney } from '@/shared/utils/format'

interface InvoiceRepriceDialogProps {
  invoiceId: string
  currencyCode: string
  open: boolean
  onOpenChange: (open: boolean) => void
}

/**
 * Recalculer les prix d'un brouillon, après avoir vu l'écart.
 *
 * **L'écart d'abord, l'écriture ensuite** (§169AM). Une facture qui bouge en
 * silence ne se contrôle plus : le dialogue montre ligne par ligne ce que le
 * barème donnerait, et n'écrit qu'à la confirmation.
 *
 * Une ligne dont le tarif a disparu s'affiche sans nouveau prix : le §169BO
 * refuse qu'un échec de calcul devienne un montant, et elle gardera le sien.
 */
export function InvoiceRepriceDialog({
  invoiceId,
  currencyCode,
  open,
  onOpenChange,
}: InvoiceRepriceDialogProps) {
  const { t } = useTranslation()

  const preview = useRepricingPreview(invoiceId, open)
  const apply = useApplyRepricing(invoiceId)

  const changes = preview.data?.changes ?? []
  const applicable = changes.filter((change) => change.newUnitPrice !== null)

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle>{t('billing.invoices.reprice.title')}</DialogTitle>
          <DialogDescription>{t('billing.invoices.reprice.hint')}</DialogDescription>
        </DialogHeader>

        {preview.isPending ? (
          <p className="text-sm text-muted-foreground">{t('common.loading')}</p>
        ) : changes.length === 0 ? (
          <p className="text-sm text-muted-foreground">{t('billing.invoices.reprice.upToDate')}</p>
        ) : (
          <ul className="flex flex-col gap-2">
            {changes.map((change) => (
              <li
                key={change.lineId}
                className="flex flex-wrap items-center justify-between gap-2 rounded-md border px-3 py-2 text-sm"
              >
                <span className="flex flex-col">
                  <span className="font-medium">{change.description}</span>
                  {change.scope ? (
                    <span className="text-xs text-muted-foreground">
                      {t(`pricing.scopes.${change.scope}`)} · {change.formula}
                    </span>
                  ) : null}
                </span>

                {change.newUnitPrice === null ? (
                  <Badge variant="destructive">{change.reason}</Badge>
                ) : (
                  <span className="flex items-center gap-2 tabular-nums">
                    <span className="text-muted-foreground line-through">
                      {formatMoney(change.currentUnitPrice, currencyCode)}
                    </span>
                    <span className="font-medium">
                      {formatMoney(change.newUnitPrice, currencyCode)}
                    </span>
                  </span>
                )}
              </li>
            ))}
          </ul>
        )}

        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            {t('common.cancel')}
          </Button>
          <Button
            disabled={applicable.length === 0 || apply.isPending}
            onClick={() => apply.mutate(undefined, { onSuccess: () => onOpenChange(false) })}
          >
            {t('billing.invoices.reprice.confirm', { count: applicable.length })}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
