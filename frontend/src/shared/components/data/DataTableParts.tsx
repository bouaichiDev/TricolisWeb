import { AlertCircle, ArrowDown, ArrowUp, ArrowUpDown, Inbox } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import type { Column } from './DataTable'
import { Button } from '@/shared/components/ui/button'
import { Skeleton } from '@/shared/components/ui/skeleton'
import { TableCell, TableRow } from '@/shared/components/ui/table'
import { cn } from '@/shared/utils/cn'

/** Indicateur de tri : neutre tant que la colonne n'est pas celle qui trie. */
export function SortIcon({ active, direction }: { active: boolean; direction: 'asc' | 'desc' }) {
  if (!active) return <ArrowUpDown className="size-3.5 opacity-40" aria-hidden />

  return direction === 'asc' ? (
    <ArrowUp className="size-3.5" aria-hidden />
  ) : (
    <ArrowDown className="size-3.5" aria-hidden />
  )
}

/**
 * Lignes de chargement.
 *
 * Elles gardent le nombre de colonnes réel : un squelette d'une seule colonne
 * ferait sauter la mise en page dès l'arrivée des données.
 */
export function LoadingRows<T>({ columns }: { columns: Column<T>[] }) {
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

/** Ligne unique occupant toute la largeur, quand la liste est vide. */
export function EmptyRow({ colSpan, message }: { colSpan: number; message?: string }) {
  const { t } = useTranslation()

  return (
    <TableRow className="hover:bg-transparent">
      <TableCell colSpan={colSpan} className="py-16">
        <div className="flex flex-col items-center gap-2 text-center">
          <Inbox className="size-8 text-muted-foreground" aria-hidden />
          <p className="font-medium">{message ?? t('table.empty')}</p>
          <p className="text-sm text-muted-foreground">{t('table.emptyHint')}</p>
        </div>
      </TableCell>
    </TableRow>
  )
}

/**
 * Échec de chargement.
 *
 * Le message du serveur est affiché tel quel : il est rédigé pour être lu, et
 * le remplacer par un texte générique priverait l'utilisateur de la seule
 * information utile.
 */
export function TableErrorState({ error, onRetry }: { error: Error; onRetry?: () => void }) {
  const { t } = useTranslation()

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
