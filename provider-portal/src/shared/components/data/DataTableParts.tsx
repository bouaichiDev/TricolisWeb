import { AlertCircle, ArrowDown, ArrowUp, ArrowUpDown, Inbox } from 'lucide-react'

import { Button } from '@/shared/components/ui/button'
import { Skeleton } from '@/shared/components/ui/skeleton'
import { TableCell, TableRow } from '@/shared/components/ui/table'
import { cn } from '@/shared/utils/cn'

import type { Column } from './DataTable'

export function SortIcon({ active, direction }: { active: boolean; direction: 'asc' | 'desc' }) {
  if (!active) return <ArrowUpDown className="size-3.5 opacity-40" aria-hidden />

  return direction === 'asc' ? <ArrowUp className="size-3.5" aria-hidden /> : <ArrowDown className="size-3.5" aria-hidden />
}

export function LoadingRows<T>({ columns }: { columns: Column<T>[] }) {
  return (
    <>
      {Array.from({ length: 5 }, (_, index) => (
        <TableRow key={index} className="hover:bg-transparent">
          {columns.map((column) => (
            <TableCell key={column.key} className={cn(column.hideOnMobile && 'hidden md:table-cell')}>
              <Skeleton className="h-4 w-full max-w-40" />
            </TableCell>
          ))}
        </TableRow>
      ))}
    </>
  )
}

export function EmptyRow({ colSpan, message }: { colSpan: number; message?: string }) {
  return (
    <TableRow className="hover:bg-transparent">
      <TableCell colSpan={colSpan} className="py-16">
        <div className="flex flex-col items-center gap-2 text-center">
          <Inbox className="text-muted-foreground size-8" aria-hidden />
          <p className="font-medium">{message ?? 'Aucun résultat'}</p>
          <p className="text-muted-foreground text-sm">Ajustez vos filtres ou créez un premier élément.</p>
        </div>
      </TableCell>
    </TableRow>
  )
}

export function TableErrorState({ message, onRetry }: { message: string; onRetry?: () => void }) {
  return (
    <div role="alert" className="bg-card flex flex-col items-center gap-3 rounded-lg border py-16 text-center">
      <AlertCircle className="text-destructive size-8" aria-hidden />
      <p className="font-medium">Le chargement a échoué</p>
      <p className="text-muted-foreground max-w-md text-sm">{message}</p>
      {onRetry ? (
        <Button variant="outline" onClick={onRetry}>
          Réessayer
        </Button>
      ) : null}
    </div>
  )
}
