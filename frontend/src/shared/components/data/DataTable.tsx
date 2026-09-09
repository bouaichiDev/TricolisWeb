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
  /**
   * Champ de filtre posé sous l'en-tête.
   *
   * Il ne filtre rien lui-même : c'est un contrôle, dont l'écran envoie la
   * valeur au serveur. Filtrer ici ne porterait que sur la page affichée, et
   * donnerait un résultat faux dès la deuxième page.
   */
  filter?: ReactNode
  className?: string
  /** Masquée sous `md` : garde la table lisible sur petit écran. */
  hideOnMobile?: boolean
}

/**
 * La colonne d'actions, définie **une fois**.
 *
 * `DataTable` la construit lui-même quand on lui passe `actions` ; ce
 * constructeur sert aux tables dont les colonnes sont fabriquées ailleurs — un
 * fichier `xxxColumns.tsx` partagé entre une liste et un onglet. Sans lui, ces
 * tables-là gardaient leur propre en-tête vide et leur propre largeur, et la
 * dernière colonne n'était pas tout à fait la même d'un écran à l'autre.
 *
 * `w-px` avec `whitespace-nowrap` : la colonne prend la largeur de ses boutons
 * et pas un pixel de plus, ce qui laisse toute la place aux données. Une largeur
 * fixe — `w-24`, `w-32`, `w-40` selon les fichiers — réservait du vide quand il
 * y avait deux boutons, et en manquait quand il y en avait quatre.
 */
export function actionsColumn<T>(header: string, cell: (row: T) => ReactNode): Column<T> {
  return { key: '__actions', header, className: 'w-px whitespace-nowrap text-right', cell }
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
  onPerPageChange?: (perPage: number) => void
  onRetry?: () => void
  /**
   * Clic sur la ligne entière.
   *
   * **À réserver aux tables qui ouvrent un panneau**, jamais pour naviguer vers
   * une fiche : une ligne cliquable rend son texte insélectionnable — tenter de
   * copier un numéro ouvrait l'écran — et n'annonce rien à un lecteur d'écran.
   * Pour aller à une fiche, c'est `actions` qui sert, ou le lien porté par la
   * colonne d'identité.
   */
  onRowClick?: (row: T) => void
  /**
   * Les actions d'une ligne, rendues dans une dernière colonne.
   *
   * La colonne est ajoutée **ici** et non déclarée par chaque écran : sa
   * position, sa largeur, son en-tête et son alignement sont les mêmes partout,
   * et trente écrans qui la déclaraient à la main donnaient trente colonnes
   * légèrement différentes — parfois avant le statut, parfois sans en-tête.
   */
  actions?: (row: T) => ReactNode
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
 *
 * Une colonne peut aussi porter un `filter` : il s'affiche sur une seconde
 * ligne d'en-tête, et l'écran qui le fournit reste responsable de transmettre
 * sa valeur au serveur — pour la même raison que le tri.
 *
 * **Les actions passent par `actions`, pas par une colonne déclarée.** C'est ce
 * qui rend la dernière colonne identique d'un écran à l'autre : même position,
 * même largeur, même en-tête, même alignement. Et c'est elle qui remplace le
 * clic sur la ligne entière — lequel rendait le texte insélectionnable, au
 * point qu'on ne pouvait pas copier un numéro sans ouvrir la fiche.
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
  onPerPageChange,
  onRetry,
  onRowClick,
  actions,
  emptyMessage,
}: DataTableProps<T>) {
  const { t } = useTranslation()

  // La colonne d'actions est toujours la **dernière**, et n'est jamais triable :
  // c'est la seule dont le contenu n'est pas une donnée de la ligne.
  const shown: Column<T>[] =
    actions === undefined ? columns : [...columns, actionsColumn(t('common.actions'), actions)]

  if (error !== null) return <TableErrorState error={error} onRetry={onRetry} />

  return (
    <div className="overflow-hidden rounded-lg border bg-card">
      <div className="overflow-x-auto">
        <Table>
          <TableHeader>
            <TableRow className="hover:bg-transparent">
              {shown.map((column) => (
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

            {shown.some((column) => column.filter !== undefined) ? (
              <TableRow className="hover:bg-transparent">
                {shown.map((column) => (
                  <TableHead
                    key={`${column.key}-filter`}
                    className={cn(
                      'pt-0 pb-2 align-top font-normal',
                      column.className,
                      column.hideOnMobile && 'hidden md:table-cell',
                    )}
                  >
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
                <TableRow
                  key={rowKey(row)}
                  onClick={onRowClick ? () => onRowClick(row) : undefined}
                  className={cn(onRowClick && 'cursor-pointer')}
                >
                  {shown.map((column) => (
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
        <DataTablePagination
          meta={meta}
          onPageChange={onPageChange}
          onPerPageChange={onPerPageChange}
        />
      ) : null}
    </div>
  )
}
