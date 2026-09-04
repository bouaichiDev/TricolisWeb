import { Pencil, Trash2 } from 'lucide-react'
import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { Button } from '@/shared/components/ui/button'

interface EntityHeaderProps {
  title: string
  subtitle?: string
  status?: string
  editTo?: string
  editPermission?: string
  onDelete?: () => void
  deletePermission?: string
  /** Actions supplementaires, avant les boutons standard. */
  actions?: ReactNode
}

/**
 * En-tete d'une fiche : identite, statut, actions.
 *
 * Mutualise ce que les fiches agence, depot, organisation, utilisateur et role
 * repetaient a l'identique. La fiche client garde le sien : le blocage y a une
 * permission propre et une confirmation specifique.
 */
export function EntityHeader({
  title,
  subtitle,
  status,
  editTo,
  editPermission,
  onDelete,
  deletePermission,
  actions,
}: EntityHeaderProps) {
  const { t } = useTranslation()

  return (
    <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
      <div className="min-w-0">
        <div className="flex flex-wrap items-center gap-3">
          <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
          {status ? <StatusBadge status={status} /> : null}
        </div>
        {subtitle ? <p className="mt-1 text-sm text-muted-foreground">{subtitle}</p> : null}
      </div>

      <div className="flex shrink-0 flex-wrap gap-2">
        {actions}

        {editTo && editPermission ? (
          <PermissionGuard permission={editPermission}>
            <Button variant="outline" asChild>
              <Link to={editTo}>
                <Pencil className="size-4" aria-hidden />
                {t('common.edit')}
              </Link>
            </Button>
          </PermissionGuard>
        ) : null}

        {onDelete && deletePermission ? (
          <PermissionGuard permission={deletePermission}>
            <Button variant="outline" onClick={onDelete}>
              <Trash2 className="size-4" aria-hidden />
              {t('common.delete')}
            </Button>
          </PermissionGuard>
        ) : null}
      </div>
    </div>
  )
}
