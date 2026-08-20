import { Boxes, Pencil, Trash2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { Button } from '@/shared/components/ui/button'

interface CatalogItemActionsProps {
  onStock: () => void
  onEdit: () => void
  onDelete: () => void
}

/**
 * Actions d'une ligne d'article, en icônes libellées au survol.
 *
 * Le stock ouvre un tiroir plutôt qu'une colonne : l'article ne porte **aucune**
 * quantité — `stock_balances` en tient une par emplacement, et une colonne du
 * tableau ne pourrait dire de quel emplacement elle parle.
 */
export function CatalogItemActions({
  onStock,
  onEdit,
  onDelete,
}: CatalogItemActionsProps) {
  const { t } = useTranslation()

  return (
    <span className="flex justify-end gap-1">
      <PermissionGuard permission="stock_balances.view">
        <Button
          variant="ghost"
          size="icon"
          title={t('stock.open')}
          aria-label={t('stock.open')}
          onClick={onStock}
        >
          <Boxes className="size-4" aria-hidden />
        </Button>
      </PermissionGuard>

      <PermissionGuard permission="catalogs.update">
        <Button
          variant="ghost"
          size="icon"
          title={t('common.edit')}
          aria-label={t('common.edit')}
          onClick={onEdit}
        >
          <Pencil className="size-4" aria-hidden />
        </Button>
      </PermissionGuard>

      <PermissionGuard permission="catalogs.delete">
        <Button
          variant="ghost"
          size="icon"
          title={t('common.delete')}
          aria-label={t('common.delete')}
          onClick={onDelete}
        >
          <Trash2 className="size-4" aria-hidden />
        </Button>
      </PermissionGuard>
    </span>
  )
}
