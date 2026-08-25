import { MapPin, Pencil, Plus, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { Button } from '@/shared/components/ui/button'

import { JourneyStepDialog } from '../components/JourneyStepDialog'
import { useDeleteTrackingDefinition, useTrackingDefinitions } from '../hooks/useTracking'
import type { TrackingEventDefinition } from '../types/trackingDefinition'

/**
 * Le parcours client : quels statuts deviennent des étapes visibles.
 *
 * Le chauffeur pose un statut — « en cours » sur un service — et l'étape
 * apparaît sur la commande, sans que personne ne la saisisse. C'est ici qu'on
 * décide lesquels comptent, sous quel titre et dans quel ordre.
 *
 * Distinct du référentiel des statuts, qui dit ce qu'un statut signifie en
 * interne : « in_progress » côté exploitation devient « Votre commande est en
 * route » côté client.
 *
 * Le tri est celui du parcours — `position` — et non celui de la création :
 * c'est l'ordre dans lequel le client verra les étapes.
 */
export function JourneyConfigurationPage() {
  const { t } = useTranslation()

  const [creating, setCreating] = useState(false)
  const [editing, setEditing] = useState<TrackingEventDefinition | null>(null)
  const [deleting, setDeleting] = useState<TrackingEventDefinition | null>(null)

  const { data, isPending, error, refetch } = useTrackingDefinitions()
  const remove = useDeleteTrackingDefinition()

  const columns: Column<TrackingEventDefinition>[] = [
    {
      key: 'position',
      header: t('journey.fields.position'),
      className: 'w-16',
      cell: (row) => <span className="font-mono text-sm">{row.position}</span>,
    },
    {
      key: 'title',
      header: t('journey.fields.title'),
      cell: (row) => (
        <span className="flex items-center gap-2">
          <span className="font-medium">{row.title}</span>
          {row.isLive ? (
            <MapPin className="size-3.5 text-muted-foreground" aria-label={t('tracking.liveStep')} />
          ) : null}
        </span>
      ),
    },
    {
      key: 'trigger',
      header: t('journey.fields.trigger'),
      // Le declencheur se lit d'un trait : quelle table, quel statut.
      cell: (row) => (
        <span className="font-mono text-xs">
          {row.sourceType} · {row.statusCode}
        </span>
      ),
    },
    {
      key: 'code',
      header: t('journey.fields.code'),
      hideOnMobile: true,
      cell: (row) => <span className="font-mono text-xs">{row.code}</span>,
    },
    {
      key: 'active',
      header: t('journey.fields.active'),
      cell: (row) => <StatusBadge status={row.active ? 'active' : 'inactive'} />,
    },
    {
      key: 'actions',
      header: '',
      className: 'w-24',
      cell: (row) => (
        <span className="flex justify-end gap-1">
          <PermissionGuard permission="tracking_event_definitions.update">
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

          <PermissionGuard permission="tracking_event_definitions.delete">
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
  ]

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={t('journey.title')}
        description={t('journey.description')}
        actions={
          <PermissionGuard permission="tracking_event_definitions.create">
            <Button size="sm" onClick={() => setCreating(true)}>
              <Plus className="size-4" aria-hidden />
              {t('journey.create')}
            </Button>
          </PermissionGuard>
        }
      />

      <DataTable
        columns={columns}
        rows={data?.data ?? []}
        rowKey={(row) => row.id}
        meta={data?.meta}
        isLoading={isPending}
        error={error}
        onRetry={() => void refetch()}
        emptyMessage={t('journey.empty')}
      />

      {creating || editing !== null ? (
        <JourneyStepDialog
          key={editing?.id ?? 'new'}
          step={editing}
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
        description={t('journey.deleteConfirm', { title: deleting?.title ?? '' })}
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
