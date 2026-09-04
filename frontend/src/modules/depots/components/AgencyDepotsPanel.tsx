import { Plus } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import { DepotTable } from './DepotTable'
import { useDeleteDepot, useDepotList } from '../hooks/useDepots'
import type { Depot } from '../types/depot'
import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { Button } from '@/shared/components/ui/button'

/** Depots d'une agence, montre dans l'onglet de la fiche agence. */
export function AgencyDepotsPanel({ agencyId }: { agencyId: string }) {
  const { t } = useTranslation()
  const [page, setPage] = useState(1)
  const [toDelete, setToDelete] = useState<Depot | null>(null)

  const { data, isPending, error, refetch } = useDepotList(agencyId, { page })
  const remove = useDeleteDepot(agencyId)

  return (
    <div className="flex flex-col gap-4">
      <PermissionGuard permission="depots.create">
        <div className="flex justify-end">
          <Button asChild size="sm">
            <Link to={`/agencies/${agencyId}/depots/create`}>
              <Plus className="size-4" aria-hidden />
              {t('depots.create')}
            </Link>
          </Button>
        </div>
      </PermissionGuard>

      <DepotTable
        agencyId={agencyId}
        rows={data?.data ?? []}
        meta={data?.meta}
        isLoading={isPending}
        error={error}
        onPageChange={setPage}
        onRetry={() => void refetch()}
        onDelete={setToDelete}
      />

      <ConfirmDialog
        open={toDelete !== null}
        onOpenChange={(open) => !open && setToDelete(null)}
        title={t('confirm.deleteTitle')}
        description={t('confirm.deleteEntity', { name: toDelete?.name ?? '' })}
        confirmLabel={t('common.delete')}
        isPending={remove.isPending}
        onConfirm={() => {
          if (toDelete === null) return
          remove.mutate(toDelete.id, { onSuccess: () => setToDelete(null) })
        }}
      />
    </div>
  )
}
