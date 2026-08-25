import { Plus } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import type { OrderService } from '@/modules/orders/types/orderDetail'
import { DataTable } from '@/shared/components/data/DataTable'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { Button } from '@/shared/components/ui/button'

import { ClaimDetailDrawer } from './ClaimDetailDrawer'
import { ClaimDialog } from './ClaimDialog'
import { claimColumns } from './claimColumns'
import { useDeleteClaim, useOrderClaims } from '../hooks/useClaims'
import type { Claim } from '../types/claim'

interface OrderClaimsTabProps {
  orderId: string
  customerId: string
  services: OrderService[]
  active: boolean
}

/**
 * Réclamations d'une commande.
 *
 * Le client et la commande sont hérités : une réclamation créée ici porte
 * forcément sur celle qu'on regarde, et sur son client. Le §15 l'exige, et le
 * chemin de création — `POST /customers/{customer}/claims` — le garantit.
 */
export function OrderClaimsTab({ orderId, customerId, services, active }: OrderClaimsTabProps) {
  const { t } = useTranslation()
  const [page, setPage] = useState(1)
  const [opened, setOpened] = useState<Claim | null>(null)
  const [editing, setEditing] = useState<Claim | null>(null)
  const [creating, setCreating] = useState(false)
  const [deleting, setDeleting] = useState<Claim | null>(null)

  const { data, isPending, error, refetch } = useOrderClaims(
    orderId,
    { page, perPage: 25, sort: 'created_at', direction: 'desc' },
    active,
  )
  const remove = useDeleteClaim()

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="font-semibold">{t('claims.title')}</p>
          <p className="text-sm text-muted-foreground">{t('claims.orderDescription')}</p>
        </div>

        <PermissionGuard permission="claims.create">
          <Button type="button" variant="outline" size="sm" onClick={() => setCreating(true)}>
            <Plus className="size-4" aria-hidden />
            {t('claims.create')}
          </Button>
        </PermissionGuard>
      </div>

      <DataTable
        columns={claimColumns({ t, onOpen: setOpened, onEdit: setEditing, onDelete: setDeleting })}
        rows={data?.data ?? []}
        rowKey={(row) => row.id}
        meta={data?.meta}
        isLoading={isPending}
        error={error}
        onPageChange={setPage}
        onRetry={() => void refetch()}
        emptyMessage={t('claims.empty')}
      />

      <ClaimDetailDrawer claim={opened} onClose={() => setOpened(null)} />

      {creating || editing !== null ? (
        <ClaimDialog
          key={editing?.id ?? 'new'}
          customerId={customerId}
          orderId={orderId}
          services={services}
          claim={editing}
          open
          onOpenChange={(open) => {
            if (open) return
            setCreating(false)
            setEditing(null)
          }}
        />
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
