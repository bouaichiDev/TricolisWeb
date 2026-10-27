import type { ReactNode } from 'react'

import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/shared/components/ui/table'
import { cn } from '@/shared/utils/cn'

import { DataTablePagination } from './DataTablePagination'
import { EmptyRow, LoadingRows, SortIcon, TableErrorState } from './DataTableParts'

export interface Column<T> {
  key: string
  header: string
  /** Clé de tri envoyée au serveur ; absente, la colonne n'est pas triable. */
  sortKey?: string
  cell: (row: T) => ReactNode
  /**
   * Champ de filtre posé sous l'en-tête, comme au back-office. Il ne filtre
   * rien lui-même : l'écran envoie sa valeur au serveur — filtrer ici ne
   * porterait que sur la page affichée.
   */
  filter?: ReactNode
  className?: string
  hideOnMobile?: boolean
}

export interface PaginationMeta {
  currentPage: number
  perPage: number
  total: number
  lastPage: number
}

interface DataTableProps<T> {
  columns: Column<T>[]
  rows: T[]
  rowKey: (row: T) => string
  meta?: PaginationMeta
  isLoading?: boolean
  errorMessage?: string | null
  sort?: string
  direction?: 'asc' | 'desc'
  onSortChange?: (sortKey: string) => void
  onPageChange?: (page: number) => void
  onPerPageChange?: (perPage: number) => void
  onRetry?: () => void
  actions?: (row: T) => ReactNode
  emptyMessage?: string
}

/**
 * Le tableau du back-office, repris tel quel : tri et pagination côté
 * serveur, filtres sous les en-têtes, choix du nombre de lignes, colonne
 * d'actions en dernier (`RowActions`), lignes squelettes au chargement.
 */
export function DataTable<T>({
  columns,
  rows,
  rowKey,
  meta,
  isLoading = false,
  errorMessage = null,
  sort,
  direction = 'asc',
  onSortChange,
  onPageChange,
  onPerPageChange,
  onRetry,
  actions,
  emptyMessage,
}: DataTableProps<T>) {
  const shown: Column<T>[] =
    actions === undefined
      ? columns
      : [...columns, { key: '__actions', header: 'Actions', className: 'w-px whitespace-nowrap text-right', cell: actions }]

  if (errorMessage !== null) return <TableErrorState message={errorMessage} onRetry={onRetry} />

  return (
    <div className="bg-card overflow-hidden rounded-lg border">
      <div className="overflow-x-auto">
        <Table>
          <TableHeader>
            <TableRow className="hover:bg-transparent">
              {shown.map((column) => (
                <TableHead key={column.key} className={cn(column.className, column.hideOnMobile && 'hidden md:table-cell')}>
                  {column.sortKey && onSortChange ? (
                    <button
                      type="button"
                      onClick={() => onSortChange(column.sortKey ?? '')}
                      className="hover:text-foreground flex items-center gap-1.5 font-medium transition-colors"
                      aria-label={sort === column.sortKey && direction === 'asc' ? 'Trier par ordre décroissant' : 'Trier par ordre croissant'}
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
            {shown.some((column) => column.filter !== undefined) ? (
              <TableRow className="hover:bg-transparent">
                {shown.map((column) => (
                  <TableHead key={`${column.key}-filter`} className={cn('pt-0 pb-2 align-top font-normal', column.className, column.hideOnMobile && 'hidden md:table-cell')}>
                    {column.filter}
                  </TableHead>
                ))}
              </TableRow>
            ) : null}
          </TableHeader>
          <TableBody>
            {isLoading ? (
              <LoadingRows columns={shown} />
            ) : rows.length === 0 ? (
              <EmptyRow colSpan={shown.length} message={emptyMessage} />
            ) : (
              rows.map((row) => (
                <TableRow key={rowKey(row)}>
                  {shown.map((column) => (
                    <TableCell key={column.key} className={cn(column.className, column.hideOnMobile && 'hidden md:table-cell')}>
                      {column.cell(row)}
                    </TableCell>
                  ))}
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </div>
      {meta && meta.total > 0 ? <DataTablePagination meta={meta} onPageChange={onPageChange} onPerPageChange={onPerPageChange} /> : null}
    </div>
  )
}
