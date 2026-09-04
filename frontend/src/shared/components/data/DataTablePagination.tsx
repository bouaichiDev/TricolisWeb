import { useTranslation } from 'react-i18next'

import { Button } from '@/shared/components/ui/button'
import type { PaginationMeta } from '@/shared/api/types'

interface DataTablePaginationProps {
  meta: PaginationMeta
  onPageChange?: (page: number) => void
}

/**
 * Pagination d'une liste.
 *
 * Les bornes affichées sont recalculées depuis `currentPage` et `perPage`
 * plutôt que lues dans `meta` : le backend ne renvoie pas `from`/`to` sur
 * toutes les routes, et un affichage absent par intermittence serait pire
 * qu'un calcul local exact.
 */
export function DataTablePagination({ meta, onPageChange }: DataTablePaginationProps) {
  const { t } = useTranslation()

  const from = (meta.currentPage - 1) * meta.perPage + 1
  const to = Math.min(meta.currentPage * meta.perPage, meta.total)

  return (
    <div className="flex flex-col gap-3 border-t px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
      <p className="text-sm text-muted-foreground">
        {t('common.showingRange', { from, to, total: meta.total })}
      </p>

      <div className="flex items-center gap-2">
        <Button
          variant="outline"
          size="sm"
          disabled={meta.currentPage <= 1}
          onClick={() => onPageChange?.(meta.currentPage - 1)}
        >
          {t('common.previous')}
        </Button>

        <span className="px-2 text-sm">
          {t('table.page', { current: meta.currentPage, total: meta.lastPage })}
        </span>

        <Button
          variant="outline"
          size="sm"
          disabled={meta.currentPage >= meta.lastPage}
          onClick={() => onPageChange?.(meta.currentPage + 1)}
        >
          {t('common.next')}
        </Button>
      </div>
    </div>
  )
}
