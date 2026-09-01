import { Pencil, Plus, Trash2 } from 'lucide-react'
import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { Alert, AlertDescription } from '@/shared/components/ui/alert'
import { Badge } from '@/shared/components/ui/badge'
import { Button } from '@/shared/components/ui/button'

import { CommunicationRuleDialog } from '../components/CommunicationRuleDialog'
import { CommunicationRuleFilterBar } from '../components/CommunicationRuleFilterBar'
import type { CommunicationRuleFilters } from '../api/communication-rules.api'
import { useCommunicationRuleList, useDeleteCommunicationRule } from '../hooks/useCommunicationRules'
import type { CommunicationRule } from '../types/communicationRule'

/**
 * Règles automatiques de communication.
 *
 * Une règle relie un événement métier à un modèle, un destinataire et un délai.
 * C'est la nouveauté de la Phase 9 : jusqu'ici, chaque message se composait à
 * la main depuis une commande.
 *
 * **Rien ne se déclenche encore.** Aucune des Phases 1 à 8 n'émet les onze
 * événements ; les règles s'enregistrent et s'appliqueront le jour où ils
 * seront émis. Le dire ici évite d'attendre des messages qui ne partiront pas.
 */
export function CommunicationRuleListPage() {
  const { t } = useTranslation()

  const [filters, setFilters] = useState<CommunicationRuleFilters>({ page: 1, perPage: 25 })
  const [creating, setCreating] = useState(false)
  const [editing, setEditing] = useState<CommunicationRule | null>(null)
  const [deleting, setDeleting] = useState<CommunicationRule | null>(null)

  const { data, isPending, error, refetch } = useCommunicationRuleList(filters)
  const remove = useDeleteCommunicationRule()

  const columns: Column<CommunicationRule>[] = useMemo(
    () => [
      {
        key: 'eventType',
        header: t('communicationRules.fields.eventType'),
        cell: (row) => (
          <span className="font-medium">{t(`communicationEvents.${row.eventType}`)}</span>
        ),
      },
      {
        key: 'service',
        header: t('communicationRules.fields.service'),
        cell: (row) =>
          row.serviceId === null ? (
            <Badge variant="outline">{t('communicationRules.allServices')}</Badge>
          ) : (
            (row.serviceName ?? row.serviceId)
          ),
      },
      {
        key: 'template',
        header: t('communicationRules.fields.template'),
        cell: (row) => row.template?.name ?? row.templateId,
      },
      {
        key: 'channel',
        header: t('communicationRules.fields.channel'),
        hideOnMobile: true,
        // Le canal vient du modele : la regle n'en porte pas, et le §158
        // interdit de lui en ajouter un.
        cell: (row) =>
          row.template?.channel == null
            ? '—'
            : t(`communicationChannels.${row.template.channel}`),
      },
      {
        key: 'recipientRole',
        header: t('communicationRules.fields.recipientRole'),
        hideOnMobile: true,
        cell: (row) => t(`recipientRoles.${row.recipientRole}`),
      },
      {
        key: 'delay',
        header: t('communicationRules.fields.delay'),
        hideOnMobile: true,
        cell: (row) =>
          row.delayValue === 0
            ? t('communicationRules.summary.immediately')
            : `${row.delayValue} ${t(`communicationRules.delayUnits.${row.delayUnit}`)}`,
      },
      {
        key: 'isAutomatic',
        header: t('communicationRules.fields.isAutomatic'),
        cell: (row) => (
          <Badge variant={row.isAutomatic ? 'secondary' : 'outline'}>
            {row.isAutomatic ? t('common.yes') : t('common.no')}
          </Badge>
        ),
      },
      {
        key: 'isActive',
        header: t('communicationRules.fields.isActive'),
        cell: (row) => <StatusBadge status={row.isActive ? 'active' : 'inactive'} />,
      },
      {
        key: 'actions',
        header: '',
        className: 'w-24',
        cell: (row) => (
          <span className="flex justify-end gap-1">
            <PermissionGuard permission="communication_rules.update">
              <Button
                variant="ghost"
                size="icon"
                title={t('common.edit')}
                aria-label={t('common.edit')}
                onClick={() => setEditing(row)}
              >
                <Pencil className="size-4" aria-hidden />
              </Button>
            </PermissionGuard>

            <PermissionGuard permission="communication_rules.delete">
              <Button
                variant="ghost"
                size="icon"
                title={t('common.delete')}
                aria-label={t('common.delete')}
                onClick={() => setDeleting(row)}
              >
                <Trash2 className="size-4" aria-hidden />
              </Button>
            </PermissionGuard>
          </span>
        ),
      },
    ],
    [t],
  )

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={t('communicationRules.title')}
        description={t('communicationRules.description')}
        actions={
          <PermissionGuard permission="communication_rules.create">
            <Button size="sm" onClick={() => setCreating(true)}>
              <Plus className="size-4" aria-hidden />
              {t('communicationRules.create')}
            </Button>
          </PermissionGuard>
        }
      />

      <Alert>
        <AlertDescription>{t('communicationRules.notWiredYet')}</AlertDescription>
      </Alert>

      <CommunicationRuleFilterBar
        filters={filters}
        onChange={(patch) => setFilters((current) => ({ ...current, ...patch }))}
      />

      <DataTable
        columns={columns}
        rows={data?.data ?? []}
        rowKey={(row) => row.id}
        meta={data?.meta}
        isLoading={isPending}
        error={error}
        onPageChange={(page) => setFilters((current) => ({ ...current, page }))}
        onRetry={() => void refetch()}
        emptyMessage={t('communicationRules.empty')}
      />

      {creating || editing !== null ? (
        <CommunicationRuleDialog
          key={editing?.id ?? 'new'}
          rule={editing}
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
        description={t('communicationRules.deleteHint')}
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
