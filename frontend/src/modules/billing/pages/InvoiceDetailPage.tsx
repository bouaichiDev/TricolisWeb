import { Lock, Pencil, Plus } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useParams } from 'react-router-dom'

import { InvoiceAddLinesDialog } from '../components/InvoiceAddLinesDialog'
import { InvoiceCloseDialog } from '../components/InvoiceCloseDialog'
import { InvoiceEditDialog } from '../components/InvoiceEditDialog'
import { InvoiceLinesTable } from '../components/InvoiceLinesTable'
import { useInvoice, useRemoveInvoiceLine } from '../hooks/useInvoices'
import type { InvoiceLine } from '../types/invoice'
import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { DetailSkeleton } from '@/shared/components/feedback/LoadingSkeleton'
import { DetailField } from '@/shared/components/layout/DetailField'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Button } from '@/shared/components/ui/button'
import { formatDate, formatMoney } from '@/shared/utils/format'

/**
 * Une facture, telle qu'elle est et telle qu'elle partira.
 *
 * **Au brouillon, tout se corrige** : l'en-tête, les lignes qu'on retire, celles
 * qu'on ajoute. C'est l'état où la facture n'engage encore personne.
 *
 * **Clôturée, elle ne s'édite plus.** Le §22 la fige : le document est peut-être
 * déjà chez le client, et le contredire en base laisserait deux vérités. Les
 * actions d'écriture disparaissent alors plutôt que d'échouer au clic.
 *
 * **Le seul changement d'état est la clôture**, et il ne se fait pas par un
 * menu de statuts : le référentiel ne connaît que `draft → closed`, et ce
 * passage déclenche les envois. Le proposer comme un statut ordinaire laisserait
 * croire qu'on peut le poser sans conséquence — le serveur le refuse d'ailleurs
 * sur la route de mise à jour.
 */
export function InvoiceDetailPage() {
  const { t } = useTranslation()
  const { id = '' } = useParams<{ id: string }>()
  const [closing, setClosing] = useState(false)
  const [editing, setEditing] = useState(false)
  const [adding, setAdding] = useState(false)
  const [toRemove, setToRemove] = useState<InvoiceLine | null>(null)

  const { data: invoice, isPending, error, refetch } = useInvoice(id)
  const removeLine = useRemoveInvoiceLine(id)

  if (isPending) return <DetailSkeleton />
  if (error) return <ErrorState error={error} onRetry={() => void refetch()} />
  if (!invoice) return null

  const editable = invoice.status === 'draft'

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={invoice.invoiceNumber}
        description={invoice.customer?.name ?? ''}
        actions={
          editable ? (
            <span className="flex flex-wrap items-center gap-2">
              <PermissionGuard permission="invoices.update">
                <Button variant="outline" onClick={() => setEditing(true)}>
                  <Pencil className="size-4" aria-hidden />
                  {t('common.edit')}
                </Button>
              </PermissionGuard>
              <PermissionGuard permission="invoices.close">
                <Button onClick={() => setClosing(true)}>
                  <Lock className="size-4" aria-hidden />
                  {t('billing.invoices.close.action')}
                </Button>
              </PermissionGuard>
            </span>
          ) : (
            <StatusBadge status={invoice.status} />
          )
        }
      />

      <SectionCard title={t('billing.invoices.sections.header')}>
        <dl className="grid gap-x-8 sm:grid-cols-3">
          <DetailField label={t('billing.invoices.fields.status')}>
            <StatusBadge status={invoice.status} />
          </DetailField>
          <DetailField label={t('billing.invoices.fields.invoiceDate')}>
            {formatDate(invoice.invoiceDate)}
          </DetailField>
          <DetailField label={t('billing.invoices.fields.period')}>
            {invoice.periodFrom || invoice.periodTo
              ? `${formatDate(invoice.periodFrom)} — ${formatDate(invoice.periodTo)}`
              : ''}
          </DetailField>
          <DetailField label={t('billing.invoices.fields.subtotal')}>
            {formatMoney(invoice.subtotal, invoice.currencyCode)}
          </DetailField>
          <DetailField label={t('billing.invoices.fields.taxTotal')}>
            {formatMoney(invoice.taxTotal, invoice.currencyCode)}
          </DetailField>
          <DetailField label={t('billing.invoices.fields.total')}>
            <span className="font-medium">{formatMoney(invoice.total, invoice.currencyCode)}</span>
          </DetailField>
          <DetailField label={t('billing.invoices.fields.externalReference')}>
            {invoice.externalReference ?? ''}
          </DetailField>
          <DetailField label={t('billing.invoices.fields.remark')}>
            {invoice.remark ?? ''}
          </DetailField>
        </dl>
      </SectionCard>

      <SectionCard
        title={t('billing.invoices.sections.lines')}
        description={editable ? undefined : t('billing.invoices.closedHint')}
        actions={
          editable ? (
            <PermissionGuard permission="invoices.update">
              <Button size="sm" variant="outline" onClick={() => setAdding(true)}>
                <Plus className="size-4" aria-hidden />
                {t('billing.invoices.addLines.action')}
              </Button>
            </PermissionGuard>
          ) : null
        }
      >
        <InvoiceLinesTable
          lines={invoice.lines ?? []}
          currencyCode={invoice.currencyCode}
          editable={editable}
          onRemove={setToRemove}
        />
      </SectionCard>

      {editing ? (
        <InvoiceEditDialog invoice={invoice} open onOpenChange={setEditing} />
      ) : null}

      {adding ? (
        <InvoiceAddLinesDialog invoice={invoice} open onOpenChange={setAdding} />
      ) : null}

      <InvoiceCloseDialog
        invoiceId={id}
        invoiceNumber={invoice.invoiceNumber}
        open={closing}
        onOpenChange={setClosing}
      />

      <ConfirmDialog
        open={toRemove !== null}
        onOpenChange={(open) => !open && setToRemove(null)}
        title={t('confirm.deleteTitle')}
        description={t('billing.invoices.lines.confirmRemove', {
          description: toRemove?.description ?? '',
        })}
        confirmLabel={t('common.delete')}
        isPending={removeLine.isPending}
        onConfirm={() => {
          if (toRemove === null) return
          removeLine.mutate(toRemove.id, { onSuccess: () => setToRemove(null) })
        }}
      />
    </div>
  )
}
