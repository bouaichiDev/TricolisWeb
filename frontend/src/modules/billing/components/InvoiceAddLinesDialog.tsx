import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { BillableServicePicker } from './BillableServicePicker'
import { EMPTY_BILLABLE_FILTERS, type BillableColumnFilters } from './billableFilters'
import { linesFromServices } from './invoiceDraft'
import { useAddInvoiceLines } from '../hooks/useInvoices'
import type { BillableService, InvoiceDetail } from '../types/invoice'
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

interface InvoiceAddLinesDialogProps {
  invoice: InvoiceDetail
  open: boolean
  onOpenChange: (open: boolean) => void
}

/**
 * Verser d'autres prestations dans une facture au brouillon.
 *
 * Le même sélecteur qu'à la création, et pour la même raison : le serveur seul
 * dit ce qui reste facturable, et une prestation déjà portée par une ligne n'y
 * figure plus — l'unicité de `invoice_lines.order_service_id` la refuserait.
 *
 * Les numéros de ligne reprennent après le dernier : les recalculer depuis un
 * ferait entrer en collision avec ceux qui existent, uniques par facture.
 */
export function InvoiceAddLinesDialog({ invoice, open, onOpenChange }: InvoiceAddLinesDialogProps) {
  const { t } = useTranslation()

  const [filters, setFilters] = useState<BillableColumnFilters>(EMPTY_BILLABLE_FILTERS)
  const [page, setPage] = useState(1)
  const [selected, setSelected] = useState<Map<string, BillableService>>(new Map())

  const add = useAddInvoiceLines(invoice.id)
  const failure = useApiMessage(add.error)
  const chosen = [...selected.values()]

  const lastNumber = (invoice.lines ?? []).reduce(
    (highest, line) => Math.max(highest, line.lineNumber),
    0,
  )

  const toggle = (service: BillableService) => {
    setSelected((current) => {
      const next = new Map(current)
      if (next.has(service.id)) next.delete(service.id)
      else next.set(service.id, service)

      return next
    })
  }

  const close = () => {
    setSelected(new Map())
    setFilters(EMPTY_BILLABLE_FILTERS)
    setPage(1)
    onOpenChange(false)
  }

  return (
    <Dialog open={open} onOpenChange={(next) => (next ? onOpenChange(true) : close())}>
      <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-5xl">
        <DialogHeader>
          <DialogTitle>{t('billing.invoices.addLines.title')}</DialogTitle>
          <DialogDescription>{t('billing.invoices.sections.servicesHint')}</DialogDescription>
        </DialogHeader>

        <FormErrorSummary message={failure} />

        <BillableServicePicker
          customerId={invoice.customerId}
          currencyCode={invoice.currencyCode}
          filters={filters}
          onFiltersChange={(patch) => {
            setFilters((current) => ({ ...current, ...patch }))
            setPage(1)
          }}
          onFiltersReset={() => {
            setFilters(EMPTY_BILLABLE_FILTERS)
            setPage(1)
          }}
          page={page}
          onPageChange={setPage}
          selected={selected}
          onToggle={toggle}
        />

        <DialogFooter className="sm:justify-between">
          <p className="text-sm text-muted-foreground">
            {t('billing.invoices.selectedCount', { count: chosen.length })}
          </p>
          <div className="flex gap-2">
            <Button variant="outline" onClick={close}>
              {t('common.cancel')}
            </Button>
            <Button
              disabled={chosen.length === 0 || add.isPending}
              onClick={() =>
                add.mutate(
                  linesFromServices(chosen, lastNumber + 1),
                  { onSuccess: close },
                )
              }
            >
              {t('billing.invoices.addLines.confirm')}
            </Button>
          </div>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
