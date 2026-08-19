import { Pencil, Trash2 } from 'lucide-react'
import type { TFunction } from 'i18next'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import type { Column } from '@/shared/components/data/DataTable'
import { Badge } from '@/shared/components/ui/badge'
import { Button } from '@/shared/components/ui/button'

import type { Status } from '../types/status'

interface Handlers {
  onEdit: (status: Status) => void
  onDelete: (status: Status) => void
}

/**
 * Colonnes du référentiel des statuts.
 *
 * L'entité est montrée deux fois : son libellé français, puis son alias
 * technique. C'est l'alias qui est stocké et qui apparaît dans les échanges ;
 * le masquer obligerait à deviner la correspondance.
 */
export function statusColumns(t: TFunction, { onEdit, onDelete }: Handlers): Column<Status>[] {
  return [
    {
      key: 'source',
      header: t('statuses.fields.source'),
      sortKey: 'source',
      cell: (row) => (
        <span className="flex flex-col">
          <span>{t(`entities.${row.source}`, { defaultValue: row.source })}</span>
          <span className="text-xs text-muted-foreground">{row.source}</span>
        </span>
      ),
    },
    {
      key: 'status',
      header: t('statuses.fields.status'),
      sortKey: 'status',
      cell: (row) => row.status,
    },
    {
      key: 'code',
      header: t('statuses.fields.code'),
      sortKey: 'code',
      cell: (row) => <span className="font-medium">{row.code}</span>,
    },
    { key: 'label', header: t('statuses.fields.label'), sortKey: 'label', cell: (row) => row.label },
    {
      key: 'icon',
      header: t('statuses.fields.icon'),
      hideOnMobile: true,
      cell: (row) => row.icon ?? '—',
    },
    {
      key: 'flags',
      header: t('statuses.fields.flags'),
      cell: (row) => (
        <span className="flex flex-wrap gap-1">
          <Badge variant={row.active ? 'secondary' : 'outline'}>
            {row.active ? t('common.enabled') : t('common.disabled')}
          </Badge>
          {row.isToSend ? <Badge variant="outline">{t('statuses.fields.isToSend')}</Badge> : null}
        </span>
      ),
    },
    {
      key: 'actions',
      header: t('common.actions'),
      cell: (row) => (
        <span className="flex gap-1">
          <PermissionGuard permission="statuses.update">
            <Button
              type="button"
              variant="ghost"
              size="sm"
              onClick={() => onEdit(row)}
              aria-label={t('statuses.edit')}
            >
              <Pencil className="size-4" aria-hidden />
            </Button>
          </PermissionGuard>

          <PermissionGuard permission="statuses.delete">
            <Button
              type="button"
              variant="ghost"
              size="sm"
              onClick={() => onDelete(row)}
              aria-label={t('common.delete')}
            >
              <Trash2 className="size-4" aria-hidden />
            </Button>
          </PermissionGuard>
        </span>
      ),
    },
  ]
}
