import { Eye, Pencil, Trash2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import type { Invoice } from '../types/invoice'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { RowActions } from '@/shared/components/data/RowActions'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import type { PaginationMeta } from '@/shared/api/types'
import { formatDate, formatMoney } from '@/shared/utils/format'

interface InvoiceTableProps {
  rows: Invoice[]
  meta?: PaginationMeta
  isLoading: boolean
  error: Error | null
  onPageChange: (page: number) => void
  onPerPageChange: (perPage: number) => void
  onRetry: () => void
  onDelete: (invoice: Invoice) => void
  onEdit: (invoice: Invoice) => void
}

/**
 * Table des factures.
 *
 * **Modifier est accessible depuis la ligne** (§169AO) : passer par la fiche
 * pour corriger une date obligeait à deux clics et un retour, alors qu'on
 * repère la facture à corriger dans la liste.
 *
 * Modifier et supprimer ne s'affichent que pour un brouillon : le §22 fige une
 * facture clôturée, et proposer un bouton que le serveur refusera userait la
 * confiance qu'on met dans l'écran.
 */
export function InvoiceTable({
  rows,
  meta,
  isLoading,
  error,
  onPageChange,
  onPerPageChange,
  onRetry,
  onDelete,
  onEdit,
}: InvoiceTableProps) {
  const { t } = useTranslation()

  const columns: Column<Invoice>[] = [
    {
      key: 'invoiceNumber',
      header: t('billing.invoices.fields.invoiceNumber'),
      cell: (row) => (
        <Link to={`/billing/invoices/${row.id}`} className="font-medium text-primary hover:underline">
          {row.invoiceNumber}
        </Link>
      ),
    },
    {
      key: 'customerName',
      header: t('billing.invoices.fields.customer'),
      cell: (row) => row.customerName ?? '',
    },
    {
      key: 'invoiceDate',
      header: t('billing.invoices.fields.invoiceDate'),
      cell: (row) => formatDate(row.invoiceDate),
    },
    {
      key: 'period',
      header: t('billing.invoices.fields.period'),
      cell: (row) =>
        row.periodFrom || row.periodTo
          ? `${formatDate(row.periodFrom)} — ${formatDate(row.periodTo)}`
          : '',
    },
    {
      key: 'total',
      header: t('billing.invoices.fields.total'),
      className: 'text-right',
      cell: (row) => (
        <span className="tabular-nums">{formatMoney(row.total, row.currencyCode)}</span>
      ),
    },
    {
      key: 'status',
      header: t('billing.invoices.fields.status'),
      cell: (row) => <StatusBadge status={row.status} />,
    },
  ]

  return (
    <DataTable
      columns={columns}
      rows={rows}
      rowKey={(row) => row.id}
      meta={meta}
      isLoading={isLoading}
      error={error}
      onPageChange={onPageChange}
      onPerPageChange={onPerPageChange}
      onRetry={onRetry}
      actions={(row) => (
        <RowActions
          actions={[
            { key: 'view', icon: Eye, label: t('common.view'), to: `/billing/invoices/${row.id}` },
            // Une facture close est figée (§22) : modifier et supprimer ne sont
            // proposés que sur un brouillon. Un bouton que le serveur refusera use la
            // confiance qu'on met dans l'écran.
            ...(row.status === 'draft'
              ? [
                  {
                    key: 'edit',
                    icon: Pencil,
                    label: t('common.edit'),
                    permission: 'invoices.update',
                    onClick: () => onEdit(row),
                  },
                  {
                    key: 'delete',
                    icon: Trash2,
                    label: t('common.delete'),
                    tone: 'danger' as const,
                    permission: 'invoices.delete',
                    onClick: () => onDelete(row),
                  },
                ]
              : []),
          ]}
        />
      )}
      emptyMessage={t('billing.invoices.empty')}
    />
  )
}
