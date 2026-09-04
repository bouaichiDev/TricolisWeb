import { Plus } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import { DepotTable } from '../components/DepotTable'
import { useDeleteDepot, useDepotList } from '../hooks/useDepots'
import type { Depot } from '../types/depot'
import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { AgencyPicker } from '@/modules/agencies/components/AgencyPicker'
import { SearchInput } from '@/shared/components/data/SearchInput'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { EmptyState } from '@/shared/components/feedback/EmptyState'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { Button } from '@/shared/components/ui/button'

/**
 * Liste des depots.
 *
 * L'API n'expose pas de `GET /depots` global : la selection d'une agence n'est
 * donc pas un filtre de confort, c'est la condition de l'appel. Tant qu'aucune
 * agence n'est choisie, l'ecran le dit plutot que d'afficher une table vide.
 */
export function DepotListPage() {
  const { t } = useTranslation()
  const [agencyId, setAgencyId] = useState('')
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [toDelete, setToDelete] = useState<Depot | null>(null)

  const { data, isPending, error, refetch } = useDepotList(agencyId, {
    page,
    search: search || undefined,
  })
  const remove = useDeleteDepot(agencyId)

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={t('depots.title')}
        description={t('depots.subtitle')}
        actions={
          agencyId ? (
            <PermissionGuard permission="depots.create">
              <Button asChild>
                <Link to={`/agencies/${agencyId}/depots/create`}>
                  <Plus className="size-4" aria-hidden />
                  {t('depots.create')}
                </Link>
              </Button>
            </PermissionGuard>
          ) : null
        }
      />

      <div className="flex flex-col gap-3 sm:flex-row">
        <AgencyPicker
          value={agencyId}
          onChange={(id) => {
            setAgencyId(id)
            setPage(1)
          }}
          className="sm:w-72"
        />
        {agencyId ? (
          <SearchInput
            value={search}
            onChange={(value) => {
              setSearch(value)
              setPage(1)
            }}
          />
        ) : null}
      </div>

      {agencyId === '' ? (
        <EmptyState title={t('depots.selectAgencyFirst')} description={t('depots.chooseAgency')} />
      ) : (
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
      )}

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
