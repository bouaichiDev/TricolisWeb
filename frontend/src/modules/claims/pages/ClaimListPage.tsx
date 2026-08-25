import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { DataTable } from '@/shared/components/data/DataTable'
import { SearchInput } from '@/shared/components/data/SearchInput'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { PageHeader } from '@/shared/components/layout/PageHeader'

import { ClaimDialog } from '../components/ClaimDialog'
import { claimColumns } from '../components/claimColumns'
import { useClaimList, useDeleteClaim } from '../hooks/useClaims'
import type { Claim, ClaimFilters } from '../types/claim'

/**
 * Réclamations de l'organisation.
 *
 * Le §17 conditionnait cette page à l'existence d'une API globale : `GET
 * /claims` existe, la page aussi.
 *
 * **Aucune création ici.** Une réclamation naît d'une commande, dont elle tire
 * son client ; la créer depuis une liste globale demanderait de choisir un
 * client, ce que le §15 cherche précisément à éviter. Cette page suit et
 * corrige, elle n'ouvre pas.
 *
 * Aucun filtre `severity` : le champ n'existe pas, et `ListClaimRequest` ne
 * l'accepterait pas.
 */
export function ClaimListPage() {
  const { t } = useTranslation()

  const [filters, setFilters] = useState<ClaimFilters>({ page: 1, perPage: 25 })
  const [editing, setEditing] = useState<Claim | null>(null)
  const [deleting, setDeleting] = useState<Claim | null>(null)

  const { data, isPending, error, refetch } = useClaimList(filters)
  const remove = useDeleteClaim()

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={t('claims.title')} description={t('claims.description')} />

      <SearchInput
        value={filters.search ?? ''}
        onChange={(search) =>
          setFilters((current) => ({ ...current, page: 1, search: search || undefined }))
        }
      />

      <DataTable
        columns={claimColumns({ t, onEdit: setEditing, onDelete: setDeleting }, true)}
        rows={data?.data ?? []}
        rowKey={(row) => row.id}
        meta={data?.meta}
        isLoading={isPending}
        error={error}
        onPageChange={(page) => setFilters((current) => ({ ...current, page }))}
        onRetry={() => void refetch()}
        emptyMessage={t('claims.empty')}
      />

      {editing !== null ? (
        <ClaimEditDialog claim={editing} onClose={() => setEditing(null)} />
      ) : null}

      <ConfirmDialog
        open={deleting !== null}
        onOpenChange={(open) => !open && setDeleting(null)}
        title={t('confirm.deleteTitle')}
        description={t('confirm.deleteEntity', { name: deleting?.title ?? '' })}
        confirmLabel={t('common.delete')}
        isPending={remove.isPending}
        onConfirm={() => {
          if (deleting === null) return
          remove.mutate(deleting.id, { onSuccess: () => setDeleting(null) })
        }}
      />
    </div>
  )
}

/**
 * Modification depuis la liste globale.
 *
 * Le dialogue veut la liste des services de la commande pour proposer le
 * contexte ; hors d'une commande, elle n'est pas chargée. Le sélecteur ne
 * montrera donc que « Toute la commande », et le service déjà lié reste tel
 * quel — ce qui vaut mieux que de charger la commande de chaque ligne.
 */
function ClaimEditDialog({ claim, onClose }: { claim: Claim; onClose: () => void }) {
  return (
    <ClaimDialog
      customerId={claim.customerId}
      orderId={claim.orderId ?? ''}
      services={[]}
      claim={claim}
      open
      onOpenChange={(open) => !open && onClose()}
    />
  )
}
