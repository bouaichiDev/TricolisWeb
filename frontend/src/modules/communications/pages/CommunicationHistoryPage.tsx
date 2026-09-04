import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { Badge } from '@/shared/components/ui/badge'
import { Tabs, TabsList, TabsTrigger } from '@/shared/components/ui/tabs'

import { CommunicationFilterBar } from '../components/CommunicationFilterBar'
import { CommunicationStatusBadge } from '../components/CommunicationStatusBadge'
import { OrderCommunicationDetailDrawer } from '../components/OrderCommunicationDetailDrawer'
import type { OrderCommunicationFilters } from '../api/order-communications.api'
import { useCommunicationHistory } from '../hooks/useOrderCommunications'
import type { OrderCommunication } from '../types/communication'

/** Les trois questions qu'on pose à un historique d'envois. */
const VIEWS = {
  all: undefined,
  scheduled: 'scheduled',
  failed: 'failed',
} as const

type ViewKey = keyof typeof VIEWS

/**
 * Historique des communications de l'organisation.
 *
 * Trois vues, un seul écran : tout, ce qui **attend** son heure, ce qui a
 * **échoué**. Les deux dernières ne sont pas des tables séparées — le §86
 * l'interdit — mais le même historique filtré par statut. Ce sont les deux
 * seules questions qui demandent une action : reprogrammer, ou relancer.
 *
 * Le contenu affiché est l'**instantané** de chaque message : ce qui est parti,
 * jamais ce que le modèle dirait aujourd'hui.
 */
export function CommunicationHistoryPage() {
  const { t } = useTranslation()

  const [view, setView] = useState<ViewKey>('all')
  const [filters, setFilters] = useState<OrderCommunicationFilters>({ page: 1, perPage: 25 })
  const [opened, setOpened] = useState<OrderCommunication | null>(null)

  const query = useCommunicationHistory({ ...filters, status: VIEWS[view] ?? filters.status })

  const columns: Column<OrderCommunication>[] = useMemo(
    () => [
      {
        key: 'createdAt',
        header: t('communications.fields.createdAt'),
        cell: (row) => new Date(row.createdAt).toLocaleString(),
      },
      {
        key: 'order',
        header: t('communications.fields.order'),
        cell: (row) => (
          <Link to={`/orders/${row.orderId}`} className="font-medium underline-offset-2 hover:underline">
            {row.orderNumber ?? row.orderId}
          </Link>
        ),
      },
      {
        key: 'channel',
        header: t('communications.fields.channel'),
        cell: (row) => t(`communicationChannels.${row.channel}`),
      },
      {
        key: 'recipient',
        header: t('communications.fields.recipient'),
        cell: (row) => (
          <span className="flex flex-col">
            <span>{row.recipientName ?? '—'}</span>
            <span className="text-xs text-muted-foreground">
              {row.recipientEmail ?? row.recipientPhone ?? ''}
            </span>
          </span>
        ),
      },
      {
        key: 'origin',
        header: t('communications.fields.origin'),
        hideOnMobile: true,
        // Aucun champ `origin` n'existe : l'origine se lit de la presence
        // d'une regle, ce que le §75 demande de ne pas inventer autrement.
        cell: (row) =>
          row.communicationRuleId === null ? (
            <Badge variant="outline">{t('communications.origins.manual')}</Badge>
          ) : (
            <Link
              to="/communications/rules"
              className="underline-offset-2 hover:underline"
              title={t('communications.origins.ruleHint')}
            >
              <Badge variant="secondary">{t('communications.origins.rule')}</Badge>
            </Link>
          ),
      },
      {
        key: 'status',
        header: t('communications.fields.status'),
        cell: (row) => <CommunicationStatusBadge status={row.status} />,
      },
      {
        key: 'timing',
        header: t('communications.fields.timing'),
        hideOnMobile: true,
        cell: (row) => {
          const stamp = row.sentAt ?? row.scheduledAt ?? row.failedAt

          return stamp === null || stamp === undefined ? '—' : new Date(stamp).toLocaleString()
        },
      },
    ],
    [t],
  )

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={t('communications.historyTitle')} description={t('communications.historyDescription')} />

      <Tabs value={view} onValueChange={(next) => setView(next as ViewKey)}>
        <TabsList>
          <TabsTrigger value="all">{t('communications.views.all')}</TabsTrigger>
          <TabsTrigger value="scheduled">{t('communications.views.scheduled')}</TabsTrigger>
          <TabsTrigger value="failed">{t('communications.views.failed')}</TabsTrigger>
        </TabsList>
      </Tabs>

      <CommunicationFilterBar
        filters={filters}
        onChange={(patch) => setFilters((current) => ({ ...current, ...patch }))}
        statusLocked={view !== 'all'}
      />

      <DataTable
        columns={columns}
        rows={query.data?.data ?? []}
        rowKey={(row) => row.id}
        meta={query.data?.meta}
        isLoading={query.isPending}
        error={query.error}
        onRowClick={(row) => setOpened(row)}
        onPageChange={(page) => setFilters((current) => ({ ...current, page }))}
        onRetry={() => void query.refetch()}
        emptyMessage={t('communications.empty')}
      />

      <OrderCommunicationDetailDrawer communication={opened} onClose={() => setOpened(null)} />
    </div>
  )
}
