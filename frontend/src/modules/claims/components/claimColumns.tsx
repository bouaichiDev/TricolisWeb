import { Pencil, Trash2 } from 'lucide-react'
import type { TFunction } from 'i18next'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import type { Column } from '@/shared/components/data/DataTable'
import { Button } from '@/shared/components/ui/button'
import { formatDate } from '@/shared/utils/format'

import type { Claim } from '../types/claim'

interface Handlers {
  t: TFunction
  onEdit: (claim: Claim) => void
  onDelete: (claim: Claim) => void
}

const dash = <span className="text-muted-foreground">—</span>

/**
 * Colonnes d'une liste de réclamations.
 *
 * Partagées entre l'onglet d'une commande et la page globale : les deux
 * montrent la même chose, seule la colonne Client n'a de sens que sur la
 * seconde — dans une commande, le client est déjà en en-tête.
 */
export function claimColumns({ t, onEdit, onDelete }: Handlers, withCustomer = false) {
  const columns: Column<Claim>[] = [
    {
      key: 'title',
      header: t('claims.fields.title'),
      cell: (row) => <span className="font-medium">{row.title}</span>,
    },
    { key: 'claimType', header: t('claims.fields.claimType'), cell: (row) => row.claimType },
    {
      key: 'status',
      header: t('claims.fields.status'),
      cell: (row) => <StatusBadge status={row.status} />,
    },
    {
      key: 'cost',
      header: t('claims.fields.cost'),
      hideOnMobile: true,
      cell: (row) => (row.cost === null ? dash : String(row.cost)),
    },
    {
      key: 'createdAt',
      header: t('claims.fields.createdAt'),
      hideOnMobile: true,
      cell: (row) => formatDate(row.createdAt),
    },
    {
      key: 'closedAt',
      header: t('claims.fields.closedAt'),
      hideOnMobile: true,
      cell: (row) => (row.closedAt === null ? dash : formatDate(row.closedAt)),
    },
    {
      key: 'actions',
      header: '',
      className: 'w-24',
      cell: (row) => (
        <span className="flex justify-end gap-1">
          <PermissionGuard permission="claims.update">
            <Button
              variant="ghost"
              size="icon"
              title={t('common.edit')}
              aria-label={t('common.edit')}
              onClick={() => onEdit(row)}
            >
              <Pencil className="size-4" aria-hidden />
            </Button>
          </PermissionGuard>

          <PermissionGuard permission="claims.delete">
            <Button
              variant="ghost"
              size="icon"
              title={t('common.delete')}
              aria-label={t('common.delete')}
              onClick={() => onDelete(row)}
            >
              <Trash2 className="size-4" aria-hidden />
            </Button>
          </PermissionGuard>
        </span>
      ),
    },
  ]

  if (!withCustomer) return columns

  return [
    columns[0],
    {
      key: 'customerName',
      header: t('claims.fields.customer'),
      cell: (row: Claim) => row.customerName ?? dash,
    },
    ...columns.slice(1),
  ]
}
