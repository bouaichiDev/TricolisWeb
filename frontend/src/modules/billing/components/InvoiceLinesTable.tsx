import { Trash2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import type { InvoiceLine } from '../types/invoice'
import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { Button } from '@/shared/components/ui/button'
import { formatMoney } from '@/shared/utils/format'

interface InvoiceLinesTableProps {
  lines: InvoiceLine[]
  currencyCode: string
  /** Une facture clôturée ne se modifie plus : le §22 la fige. */
  editable: boolean
  onRemove: (line: InvoiceLine) => void
}

/**
 * Les lignes de la facture.
 *
 * L'adresse affichée vient du **cliché** pris à la création, pas de l'adresse
 * vivante du client : le §13 veut qu'une facture d'août continue de montrer
 * l'adresse d'août, même après un déménagement.
 */
export function InvoiceLinesTable({
  lines,
  currencyCode,
  editable,
  onRemove,
}: InvoiceLinesTableProps) {
  const { t } = useTranslation()

  const columns: Column<InvoiceLine>[] = [
    {
      key: 'lineNumber',
      header: t('billing.invoices.lines.number'),
      className: 'w-12',
      cell: (row) => row.lineNumber,
    },
    {
      key: 'description',
      header: t('billing.invoices.lines.description'),
      cell: (row) => (
        <span className="flex flex-col">
          <span>{row.description}</span>
          <span className="text-xs text-muted-foreground">
            {[row.serviceCode, row.customerOrderReference].filter(Boolean).join(' · ')}
          </span>
        </span>
      ),
    },
    {
      key: 'address',
      header: t('billing.invoices.lines.address'),
      cell: (row) =>
        row.addressSnapshot
          ? [row.addressSnapshot.postalCode, row.addressSnapshot.city].filter(Boolean).join(' ')
          : '',
    },
    {
      key: 'quantity',
      header: t('billing.invoices.lines.quantity'),
      className: 'text-right',
      cell: (row) => <span className="tabular-nums">{row.quantity}</span>,
    },
    {
      key: 'unitPrice',
      header: t('billing.invoices.lines.unitPrice'),
      className: 'text-right',
      cell: (row) => <span className="tabular-nums">{formatMoney(row.unitPrice, currencyCode)}</span>,
    },
    {
      key: 'totalExcludingTax',
      header: t('billing.invoices.lines.totalExcludingTax'),
      className: 'text-right',
      cell: (row) => (
        <span className="tabular-nums">{formatMoney(row.totalExcludingTax, currencyCode)}</span>
      ),
    },
    {
      key: 'order',
      header: '',
      className: 'w-10',
      cell: (row) =>
        row.orderId ? (
          <Link to={`/orders/${row.orderId}`} className="text-xs text-primary hover:underline">
            {t('billing.invoices.lines.order')}
          </Link>
        ) : null,
    },
    {
      key: 'actions',
      header: '',
      className: 'w-12',
      cell: (row) =>
        editable ? (
          <PermissionGuard permission="invoices.update">
            <Button
              variant="ghost"
              size="icon"
              aria-label={t('common.delete')}
              onClick={() => onRemove(row)}
            >
              <Trash2 className="size-4" aria-hidden />
            </Button>
          </PermissionGuard>
        ) : null,
    },
  ]

  return (
    <DataTable
      columns={columns}
      rows={lines}
      rowKey={(row) => row.id}
      emptyMessage={t('billing.invoices.lines.empty')}
    />
  )
}
