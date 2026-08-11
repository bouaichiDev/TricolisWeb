import { AlertCircle, ArrowDown, ArrowUp, ArrowUpDown, Inbox } from 'lucide-react'
import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'

import { Button } from '@/shared/components/ui/button'
import { Skeleton } from '@/shared/components/ui/skeleton'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/shared/components/ui/table'
import type { PaginationMeta } from '@/shared/api/types'
import { cn } from '@/shared/utils/cn'

export interface Column<T> {
  key: string
  header: string
  /** Nom de colonne accepté par le tri serveur. Absent = non triable. */
  sortKey?: string
  cell: (row: T) => ReactNode
  className?: string
  /** Masquée sous `md` : garde la table lisible sur petit écran. */
  hideOnMobile?: boolean
}

interface DataTableProps<T> {
  columns: Column<T>[]
  rows: T[]
  rowKey: (row: T) => string
  meta?: PaginationMeta
  isLoading?: boolean
  error?: Error | null
  sort?: string
  direction?: 'asc' | 'desc'
  onSortChange?: (sortKey: string) => void
  onPageChange?: (page: number) => void
  onRetry?: () => void
  onRowClick?: (row: T) => void
  emptyMessage?: string
}

/**
 * Table de liste, adossée à la pagination serveur.
 *
 * Le tri et la pagination sont **délégués au backend** : le §26 l'impose, et
 * l'API le rend obligatoire de toute façon — une liste paginée ne contient
 * qu'une page, trier ces 25 lignes localement donnerait un ordre faux.
 *
 * Les colonnes triables sont déclarées par `sortKey`, qui doit correspondre à
 * la liste blanche du module côté serveur ; toute autre valeur renvoie 422.
 */
export function DataTable<T>({
  columns,
  rows,
  rowKey,
  meta,
  isLoading = false,
  error = null,
  sort,
  direction = 'asc',
  onSortChange,
  onPageChange,
  onRetry,
  onRowClick,
  emptyMessage,
}: DataTableProps<T>) {
  const { t } = useTranslation()

  if (error !== null) {
    return (
      <div className="flex flex-col items-center gap-3 rounded-lg border bg-card py-16 text-center">
        <AlertCircle className="size-8 text-destructive" aria-hidden />
        <p className="font-medium">{t('table.error')}</p>
        <p className="max-w-md text-sm text-muted-foreground">{error.message}</p>
        {onRetry ? (
          <Button variant="outline" onClick={onRetry}>
            {t('common.retry')}
          </Button>
        ) : null}
      </div>
    )
  }

  return (
    <div className="overflow-hidden rounded-lg border bg-card">
      <div className="overflow-x-auto">
        <Table>
          <TableHeader>
            <TableRow className="hover:bg-transparent">
              {columns.map((column) => (
                <TableHead
                  key={column.key}
                  className={cn(column.className, column.hideOnMobile && 'hidden md:table-cell')}
                >
                  {column.sortKey && onSortChange ? (
                    <button
                      type="button"
                      onClick={() => onSortChange(column.sortKey ?? '')}
                      className="flex items-center gap-1.5 font-medium transition-colors hover:text-foreground"
                      aria-label={
                        sort === column.sortKey && direction === 'asc'
                          ? t('table.sortDescending')
                          : t('table.sortAscending')
                      }
                    >
                      {column.header}
                      <SortIcon active={sort === column.sortKey} direction={direction} />
                    </button>
                  ) : (
                    column.header
                  )}
                </TableHead>
              ))}
            </TableRow>
          </TableHeader>

          <TableBody>
            {isLoading ? (
              <LoadingRows columns={columns} />
            ) : rows.length === 0 ? (
              <TableRow className="hover:bg-transparent">
                <TableCell colSpan={columns.length} className="py-16">
                  <div className="flex flex-col items-center gap-2 text-center">
                    <Inbox className="size-8 text-muted-foreground" aria-hidden />
                    <p className="font-medium">{emptyMessage ?? t('table.empty')}</p>
                    <p className="text-sm text-muted-foreground">{t('table.emptyHint')}</p>
                  </div>
                </TableCell>
              </TableRow>
            ) : (
              rows.map((row) => (
                <TableRow
                  key={rowKey(row)}
                  onClick={onRowClick ? () => onRowClick(row) : undefined}
                  className={cn(onRowClick && 'cursor-pointer')}
                >
                  {columns.map((column) => (
                    <TableCell
                      key={column.key}
                      className={cn(
                        column.className,
                        column.hideOnMobile && 'hidden md:table-cell',
                      )}
                    >
                      {column.cell(row)}
                    </TableCell>
                  ))}
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </div>

      {meta && meta.total > 0 ? (
        <DataTablePagination meta={meta} onPageChange={onPageChange} />
      ) : null}
    </div>
  )
}

function SortIcon({ active, direction }: { active: boolean; direction: 'asc' | 'desc' }) {
  if (!active) return <ArrowUpDown className="size-3.5 opacity-40" aria-hidden />

  return direction === 'asc' ? (
    <ArrowUp className="size-3.5" aria-hidden />
  ) : (
    <ArrowDown className="size-3.5" aria-hidden />
  )
}

function LoadingRows<T>({ columns }: { columns: Column<T>[] }) {
  return (
    <>
      {Array.from({ length: 5 }, (_, index) => (
        <TableRow key={index} className="hover:bg-transparent">
          {columns.map((column) => (
            <TableCell
              key={column.key}
              className={cn(column.hideOnMobile && 'hidden md:table-cell')}
            >
              <Skeleton className="h-4 w-full max-w-40" />
            </TableCell>
          ))}
        </TableRow>
      ))}
    </>
  )
}

function DataTablePagination({
  meta,
  onPageChange,
}: {
  meta: PaginationMeta
  onPageChange?: (page: number) => void
}) {
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
