import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { InvoiceHeaderFields, type InvoiceHeaderState } from './InvoiceHeaderFields'
import { useUpdateInvoice } from '../hooks/useInvoices'
import type { InvoiceDetail } from '../types/invoice'
import { FormErrorSummary } from '@/shared/components/form/FormErrorSummary'
import { Button } from '@/shared/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/shared/components/ui/dialog'
import { useApiMessage } from '@/shared/hooks/useApiMessage'

interface InvoiceEditDialogProps {
  invoice: InvoiceDetail
  open: boolean
  onOpenChange: (open: boolean) => void
}

/**
 * Corriger l'en-tête d'une facture au brouillon.
 *
 * **Tant qu'elle n'est pas clôturée**, et pas au-delà : le §22 fige un document
 * que le client détient peut-être déjà. Le serveur refuse de toute façon, mais
 * l'écran ne doit pas proposer ce qui sera refusé.
 *
 * Le client ne se change pas : les lignes renvoient à ses commandes, et en
 * changer les rendrait incohérentes. Le champ reste donc verrouillé — c'est la
 * même règle qu'à la création, où il se fige dès la première prestation.
 */
export function InvoiceEditDialog({ invoice, open, onOpenChange }: InvoiceEditDialogProps) {
  const { t } = useTranslation()
  const update = useUpdateInvoice(invoice.id)
  const failure = useApiMessage(update.error)

  const [header, setHeader] = useState<InvoiceHeaderState>({
    customerId: invoice.customerId,
    invoiceNumber: invoice.invoiceNumber,
    invoiceDate: invoice.invoiceDate ?? '',
    currencyCode: invoice.currencyCode,
    externalReference: invoice.externalReference ?? '',
    remark: invoice.remark ?? '',
  })

  const [period, setPeriod] = useState({
    from: invoice.periodFrom ?? '',
    to: invoice.periodTo ?? '',
  })

  const ready =
    header.invoiceNumber.trim() !== '' &&
    header.invoiceDate !== '' &&
    header.currencyCode.length === 3

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle>{t('billing.invoices.edit.title')}</DialogTitle>
          <DialogDescription>{t('billing.invoices.edit.hint')}</DialogDescription>
        </DialogHeader>

        <FormErrorSummary message={failure} />

        <InvoiceHeaderFields value={header} onChange={setHeader} customerLocked />

        <div className="grid gap-4 sm:grid-cols-2">
          <label className="flex flex-col gap-2 text-sm font-medium">
            {t('billing.invoices.picker.periodFrom')}
            <input
              type="date"
              value={period.from}
              onChange={(event) => setPeriod({ ...period, from: event.target.value })}
              className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
            />
          </label>
          <label className="flex flex-col gap-2 text-sm font-medium">
            {t('billing.invoices.picker.periodTo')}
            <input
              type="date"
              value={period.to}
              onChange={(event) => setPeriod({ ...period, to: event.target.value })}
              className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
            />
          </label>
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            {t('common.cancel')}
          </Button>
          <Button
            disabled={!ready || update.isPending}
            onClick={() =>
              update.mutate(
                {
                  invoiceNumber: header.invoiceNumber.trim(),
                  invoiceDate: header.invoiceDate,
                  periodFrom: period.from || null,
                  periodTo: period.to || null,
                  currencyCode: header.currencyCode,
                  externalReference: header.externalReference || null,
                  remark: header.remark || null,
                },
                { onSuccess: () => onOpenChange(false) },
              )
            }
          >
            {t('common.save')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
