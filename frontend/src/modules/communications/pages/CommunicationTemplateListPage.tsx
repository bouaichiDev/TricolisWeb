import { Pencil, Plus, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { SearchInput } from '@/shared/components/data/SearchInput'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { Button } from '@/shared/components/ui/button'

import { CommunicationTemplateDialog } from '../components/CommunicationTemplateDialog'
import type { CommunicationTemplateFilters } from '../api/communication-templates.api'
import {
  useCommunicationTemplateList,
  useDeleteCommunicationTemplate,
} from '../hooks/useCommunicationTemplates'
import type { CommunicationTemplate } from '../types/communication'

/**
 * Modèles de message de l'organisation.
 *
 * C'est ici que se prépare le contenu utilisé depuis une commande : « Client
 * absent », « Rappel de rendez-vous » et le reste sont des lignes de cette
 * table, pas des cas codés dans l'écran des communications.
 *
 * Les **règles** de communication ne sont pas gérées ici, ni ailleurs : elles
 * sont hors périmètre de cette phase. La colonne `rulesCount` existe côté
 * serveur et n'est pas affichée — elle n'apprendrait rien d'actionnable.
 */
export function CommunicationTemplateListPage() {
  const { t } = useTranslation()

  const [filters, setFilters] = useState<CommunicationTemplateFilters>({ page: 1, perPage: 25 })
  const [creating, setCreating] = useState(false)
  const [editing, setEditing] = useState<CommunicationTemplate | null>(null)
  const [deleting, setDeleting] = useState<CommunicationTemplate | null>(null)

  const { data, isPending, error, refetch } = useCommunicationTemplateList(filters)
  const remove = useDeleteCommunicationTemplate()

  const columns: Column<CommunicationTemplate>[] = [
    {
      key: 'name',
      header: t('communicationTemplates.fields.name'),
      cell: (row) => <span className="font-medium">{row.name}</span>,
    },
    {
      key: 'code',
      header: t('communicationTemplates.fields.code'),
      cell: (row) => <span className="font-mono text-sm">{row.code}</span>,
    },
    {
      key: 'channel',
      header: t('communicationTemplates.fields.channel'),
      cell: (row) => t(`communicationChannels.${row.channel}`),
    },
    {
      key: 'templateType',
      header: t('communicationTemplates.fields.templateType'),
      hideOnMobile: true,
      cell: (row) => t(`communicationTemplateTypes.${row.templateType}`),
    },
    {
      key: 'language',
      header: t('communicationTemplates.fields.language'),
      hideOnMobile: true,
      cell: (row) => row.language.toUpperCase(),
    },
    {
      key: 'isActive',
      header: t('communicationTemplates.fields.isActive'),
      cell: (row) => <StatusBadge status={row.isActive ? 'active' : 'inactive'} />,
    },
    {
      key: 'actions',
      header: '',
      className: 'w-24',
      cell: (row) => (
        <span className="flex justify-end gap-1">
          <PermissionGuard permission="communication_templates.update">
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

          <PermissionGuard permission="communication_templates.delete">
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
        title={t('communicationTemplates.title')}
        description={t('communicationTemplates.description')}
        actions={
          <PermissionGuard permission="communication_templates.create">
            <Button size="sm" onClick={() => setCreating(true)}>
              <Plus className="size-4" aria-hidden />
              {t('communicationTemplates.create')}
            </Button>
          </PermissionGuard>
        }
      />

      <SearchInput
        value={filters.search ?? ''}
        onChange={(search) =>
          setFilters((current) => ({ ...current, page: 1, search: search || undefined }))
        }
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
        emptyMessage={t('communicationTemplates.empty')}
      />

      {creating || editing !== null ? (
        <CommunicationTemplateDialog
          key={editing?.id ?? 'new'}
          template={editing}
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
        description={t('confirm.deleteEntity', { name: deleting?.name ?? '' })}
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
