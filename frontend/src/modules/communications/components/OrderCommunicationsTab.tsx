import { Plus } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { Button } from '@/shared/components/ui/button'
import { formatDateTime } from '@/shared/utils/format'

import { CommunicationRowActions } from './CommunicationRowActions'
import { CommunicationStatusBadge } from './CommunicationStatusBadge'
import { CreateOrderCommunicationDialog } from './CreateOrderCommunicationDialog'
import { OrderCommunicationDetailDrawer } from './OrderCommunicationDetailDrawer'
import { useOrderCommunications } from '../hooks/useOrderCommunications'
import type { OrderCommunication } from '../types/communication'

interface OrderCommunicationsTabProps {
  orderId: string
  active: boolean
}

const dash = <span className="text-muted-foreground">—</span>

/**
 * Communications d'une commande.
 *
 * L'historique montre les **snapshots** : ce qui est parti, avec le destinataire
 * et le sujet d'alors. Un template modifié depuis ne réécrit rien.
 *
 * Aucun bouton n'est codé par scénario — « Client absent », « Rappel de
 * rendez-vous » et le reste sont des templates. Le §29 l'exige, et c'est ce qui
 * permet au métier d'ajouter un cas sans toucher au code.
 */
export function OrderCommunicationsTab({ orderId, active }: OrderCommunicationsTabProps) {
  const { t } = useTranslation()
  const [page, setPage] = useState(1)
  const [opened, setOpened] = useState<OrderCommunication | null>(null)
  const [creating, setCreating] = useState(false)

  const { data, isPending, error, refetch } = useOrderCommunications(
    orderId,
    { page, perPage: 25, sort: 'created_at', direction: 'desc' },
    active,
  )

  const columns: Column<OrderCommunication>[] = [
    {
      key: 'subject',
      header: t('communications.fields.subject'),
      cell: (row) => (
        <button
          type="button"
          className="text-left font-medium underline-offset-2 hover:underline"
          onClick={() => setOpened(row)}
        >
          {row.subject ?? row.template?.name ?? t('communications.untitled')}
        </button>
      ),
    },
    {
      key: 'channel',
      header: t('communications.fields.channel'),
      cell: (row) => t(`communicationChannels.${row.channel}`),
    },
    {
      key: 'recipientRole',
      header: t('communications.fields.recipientRole'),
      hideOnMobile: true,
      cell: (row) => t(`recipientRoles.${row.recipientRole}`),
    },
    {
      key: 'status',
      header: t('communications.fields.status'),
      cell: (row) => <CommunicationStatusBadge status={row.status} />,
    },
    {
      key: 'sentAt',
      header: t('communications.fields.sentAt'),
      hideOnMobile: true,
      cell: (row) => (row.sentAt === null ? dash : formatDateTime(row.sentAt)),
    },
    {
      key: 'actions',
      header: '',
      className: 'w-40',
      cell: (row) => <CommunicationRowActions communication={row} />,
    },
  ]

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="font-semibold">{t('communications.title')}</p>
          <p className="text-sm text-muted-foreground">{t('communications.description')}</p>
        </div>

        <PermissionGuard permission="order_communications.create">
          <Button type="button" variant="outline" size="sm" onClick={() => setCreating(true)}>
            <Plus className="size-4" aria-hidden />
            {t('communications.create')}
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
        emptyMessage={t('communications.empty')}
      />

      <OrderCommunicationDetailDrawer
        communication={opened}
        onClose={() => setOpened(null)}
      />

      {creating ? (
        <CreateOrderCommunicationDialog orderId={orderId} open onOpenChange={setCreating} />
      ) : null}
    </div>
  )
}
