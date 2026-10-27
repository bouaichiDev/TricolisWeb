import { Button } from '@/shared/components/ui/button'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/shared/components/ui/select'

import type { PaginationMeta } from './DataTable'

const PAGE_SIZES = [10, 25, 50, 100]

interface Props {
  meta: PaginationMeta
  onPageChange?: (page: number) => void
  onPerPageChange?: (perPage: number) => void
}

/** Pied de tableau du back-office : plage affichée, taille de page, navigation. */
export function DataTablePagination({ meta, onPageChange, onPerPageChange }: Props) {
  const from = (meta.currentPage - 1) * meta.perPage + 1
  const to = Math.min(meta.currentPage * meta.perPage, meta.total)
  const sizes = (PAGE_SIZES.includes(meta.perPage) ? PAGE_SIZES : [meta.perPage, ...PAGE_SIZES]).sort((a, b) => a - b)

  return (
    <div className="flex flex-col gap-3 border-t px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
      <div className="flex items-center gap-4">
        <p className="text-muted-foreground text-sm">
          Affichage de {from} à {to} sur {meta.total} {meta.total > 1 ? 'résultats' : 'résultat'}
        </p>
        {onPerPageChange ? (
          <div className="flex items-center gap-2">
            <span className="text-muted-foreground hidden text-sm sm:inline">Lignes par page</span>
            <Select value={String(meta.perPage)} onValueChange={(value) => onPerPageChange(Number(value))}>
              <SelectTrigger size="sm" className="w-[4.5rem]" aria-label="Lignes par page">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {sizes.map((size) => (
                  <SelectItem key={size} value={String(size)}>
                    {size}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
        ) : null}
      </div>
      <div className="flex items-center gap-2">
        <Button variant="outline" size="sm" disabled={meta.currentPage <= 1} onClick={() => onPageChange?.(meta.currentPage - 1)}>
          Précédent
        </Button>
        <span className="px-2 text-sm">
          Page {meta.currentPage} sur {meta.lastPage}
        </span>
        <Button variant="outline" size="sm" disabled={meta.currentPage >= meta.lastPage} onClick={() => onPageChange?.(meta.currentPage + 1)}>
          Suivant
        </Button>
      </div>
    </div>
  )
}
