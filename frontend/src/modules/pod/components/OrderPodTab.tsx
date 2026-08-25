import { Plus } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import type { OrderService } from '@/modules/orders/types/orderDetail'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { Button } from '@/shared/components/ui/button'
import { formatDateTime } from '@/shared/utils/format'

import { NewPodDialog } from './NewPodDialog'
import { PodDetailDialog } from './PodDetailDialog'
import { useOrderPods } from '../hooks/usePod'
import type { ProofOfDelivery } from '../types/proofOfDelivery'

interface OrderPodTabProps {
  orderId: string
  services: OrderService[]
  active: boolean
}

/**
 * Preuves de livraison d'une commande.
 *
 * Une preuve ne se modifie ni ne s'efface : `proofs-of-delivery` n'expose que
 * `index`, `store` et `show`, et le module n'a que `view` et `create`. Une
 * livraison mal constatée se corrige par une réclamation, pas en réécrivant la
 * preuve — c'est ce qui lui donne sa valeur.
 */
export function OrderPodTab({ orderId, services, active }: OrderPodTabProps) {
  const { t } = useTranslation()
  const [page, setPage] = useState(1)
  const [opened, setOpened] = useState<ProofOfDelivery | null>(null)
  const [creating, setCreating] = useState(false)

  const { data, isPending, error, refetch } = useOrderPods(
    orderId,
    { page, perPage: 25, sort: 'delivered_at', direction: 'desc' },
    active,
  )

  const serviceName = (id: string | null) => {
    if (id === null) return <span className="text-muted-foreground">—</span>
    const service = services.find((item) => item.id === id)

    return service?.service?.name ?? service?.serviceNumber ?? id
  }

  const columns: Column<ProofOfDelivery>[] = [
    {
      key: 'recipientName',
      header: t('pod.fields.recipientName'),
      cell: (row) => <span className="font-medium">{row.recipientName}</span>,
    },
    {
      key: 'deliveredAt',
      header: t('pod.fields.deliveredAt'),
      cell: (row) => formatDateTime(row.deliveredAt),
    },
    {
      key: 'orderService',
      header: t('pod.fields.orderService'),
      hideOnMobile: true,
      cell: (row) => serviceName(row.orderServiceId),
    },
    {
      key: 'proofs',
      header: t('pod.fields.proofs'),
      hideOnMobile: true,
      // Presence seulement : le nom du fichier vit dans le detail.
      cell: (row) =>
        [
          row.signatureDocumentId ? t('pod.fields.signature') : null,
          row.photoDocumentId ? t('pod.fields.photo') : null,
        ]
          .filter(Boolean)
          .join(' · ') || <span className="text-muted-foreground">—</span>,
    },
    {
      key: 'actions',
      header: '',
      className: 'w-24',
      cell: (row) => (
        <span className="flex justify-end">
          <Button type="button" variant="ghost" size="sm" onClick={() => setOpened(row)}>
            {t('pod.openDetail')}
          </Button>
        </span>
      ),
    },
  ]

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="font-semibold">{t('pod.title')}</p>
          <p className="text-sm text-muted-foreground">{t('pod.description')}</p>
        </div>

        <PermissionGuard permission="proofs_of_delivery.create">
          <Button type="button" variant="outline" size="sm" onClick={() => setCreating(true)}>
            <Plus className="size-4" aria-hidden />
            {t('pod.add')}
          </Button>
        </PermissionGuard>
      </div>

      <DataTable
        columns={columns}
        rows={data?.data ?? []}
        rowKey={(row) => row.id}
        meta={data?.meta}
        isLoading={isPending}
        error={error}
        onPageChange={setPage}
        onRetry={() => void refetch()}
        emptyMessage={t('pod.empty')}
      />

      <PodDetailDialog pod={opened} onClose={() => setOpened(null)} />

      {creating ? (
        <NewPodDialog
          orderId={orderId}
          services={services}
          open
          onOpenChange={setCreating}
        />
      ) : null}
    </div>
  )
}
