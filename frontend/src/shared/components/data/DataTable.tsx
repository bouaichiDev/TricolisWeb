import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'

import { DataTablePagination } from './DataTablePagination'
import { EmptyRow, LoadingRows, SortIcon, TableErrorState } from './DataTableParts'
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

  if (error !== null) return <TableErrorState error={error} onRetry={onRetry} />

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
              <EmptyRow colSpan={columns.length} message={emptyMessage} />
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
                      className={cn(column.className, column.hideOnMobile && 'hidden md:table-cell')}
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
