import { useTranslation } from 'react-i18next'

import { Button } from '@/shared/components/ui/button'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/shared/components/ui/select'
import type { PaginationMeta } from '@/shared/api/types'

/**
 * Les tailles de page proposées.
 *
 * Cent est la borne du serveur — `ListRequest` refuse au-delà — et ce n'est pas
 * une limite arbitraire : au-delà, la page transporte plus de lignes que
 * personne n'en lit, et la table devient un fichier à faire défiler. Dix sert
 * aux écrans étroits et aux tables posées dans un onglet.
 */
const PAGE_SIZES = [10, 25, 50, 100]

interface DataTablePaginationProps {
  meta: PaginationMeta
  onPageChange?: (page: number) => void
  onPerPageChange?: (perPage: number) => void
}

/**
 * Pagination d'une liste.
 *
 * Les bornes affichées sont recalculées depuis `currentPage` et `perPage`
 * plutôt que lues dans `meta` : le backend ne renvoie pas `from`/`to` sur
 * toutes les routes, et un affichage absent par intermittence serait pire
 * qu'un calcul local exact.
 *
 * Le choix du nombre de lignes n'apparaît que si l'écran sait quoi en faire :
 * la taille de page voyage jusqu'au serveur, et un sélecteur qui ne
 * changerait rien vaudrait moins que pas de sélecteur du tout.
 *
 * Changer de taille **ramène à la première page**, et c'est l'écran appelant
 * qui s'en charge : passer de vingt-cinq à cent lignes en restant page 4
 * afficherait une page qui n'existe plus, donc une table vide.
 */
export function DataTablePagination({
  meta,
  onPageChange,
  onPerPageChange,
}: DataTablePaginationProps) {
  const { t } = useTranslation()

  const from = (meta.currentPage - 1) * meta.perPage + 1
  const to = Math.min(meta.currentPage * meta.perPage, meta.total)

  return (
    <div className="flex flex-col gap-3 border-t px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
      <div className="flex items-center gap-4">
        <p className="text-sm text-muted-foreground">
          {t('common.showingRange', { from, to, total: meta.total })}
        </p>

        {onPerPageChange ? (
          <div className="flex items-center gap-2">
            <span className="hidden text-sm text-muted-foreground sm:inline">
              {t('table.rowsPerPage')}
            </span>
            <Select
              value={String(meta.perPage)}
              onValueChange={(value) => onPerPageChange(Number(value))}
            >
              <SelectTrigger size="sm" className="w-[4.5rem]" aria-label={t('table.rowsPerPage')}>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {/* La taille courante est ajoutée si elle ne figure pas dans la
                    liste : une table posée avec `perPage: 5` afficherait sinon
                    un sélecteur vide, qui a l'air cassé. */}
                {(PAGE_SIZES.includes(meta.perPage) ? PAGE_SIZES : [meta.perPage, ...PAGE_SIZES])
                  .sort((a, b) => a - b)
                  .map((size) => (
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
