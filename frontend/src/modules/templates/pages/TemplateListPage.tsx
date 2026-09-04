import { Pencil, Plus, Trash2 } from 'lucide-react'
import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useSearchParams } from 'react-router-dom'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { Badge } from '@/shared/components/ui/badge'
import { Button } from '@/shared/components/ui/button'

import { TemplateDialog } from '../components/TemplateDialog'
import { TemplateFilterBar } from '../components/TemplateFilterBar'
import { GLOBAL_SCOPE, type TemplateFilters } from '../api/templates.api'
import { useDeleteTemplate, useTemplateList } from '../hooks/useTemplates'
import type { Template } from '../types/template'

/**
 * Modèles de l'organisation — messages **et** documents.
 *
 * Un seul écran, une seule table, une seule API. Le menu y mène par deux
 * portes — « Communication › Templates » et « Facturation › Templates de
 * facture » — parce qu'un exploitant et un comptable n'y cherchent pas la même
 * chose ; ils arrivent au même endroit, avec un filtre différent.
 *
 * Le filtre d'arrivée vient de l'URL. Ouvrir la page depuis la facturation ne
 * doit pas obliger à re-sélectionner « facture » pour voir ce qu'on venait
 * voir.
 */
export function TemplateListPage() {
  const { t } = useTranslation()
  const [params] = useSearchParams()

  const initialType = params.get('templateType') ?? undefined

  const [filters, setFilters] = useState<TemplateFilters>(() => ({
    page: 1,
    perPage: 25,
    templateType: initialType,
  }))
  const [creating, setCreating] = useState(false)
  const [editing, setEditing] = useState<Template | null>(null)
  const [deleting, setDeleting] = useState<Template | null>(null)

  const { data, isPending, error, refetch } = useTemplateList(filters)
  const remove = useDeleteTemplate()

  const invoiceMode = filters.templateType === 'invoice'

  const columns: Column<Template>[] = useMemo(
    () => [
      {
        key: 'name',
        header: t('templates.fields.name'),
        cell: (row) => <span className="font-medium">{row.name}</span>,
      },
      {
        key: 'code',
        header: t('templates.fields.code'),
        cell: (row) => <span className="font-mono text-sm">{row.code}</span>,
      },
      {
        key: 'templateType',
        header: t('templates.fields.templateType'),
        cell: (row) => t(`templateTypes.${row.templateType}`),
      },
      {
        key: 'scope',
        header: t('templates.fields.customer'),
        cell: (row) =>
          row.customerId === null ? (
            <Badge variant="outline">{t('templates.globalScope')}</Badge>
          ) : (
            (row.customerName ?? row.customerId)
          ),
      },
      {
        key: 'channel',
        header: t('templates.fields.channel'),
        hideOnMobile: true,
        // Un document n'a pas de canal : afficher un tiret plutot qu'un vide
        // dit que c'est voulu, pas que la donnee manque.
        cell: (row) =>
          row.channel === null ? (
            <span className="text-muted-foreground">{t('templates.noChannel')}</span>
          ) : (
            t(`communicationChannels.${row.channel}`)
          ),
      },
      {
        key: 'language',
        header: t('templates.fields.language'),
        hideOnMobile: true,
        cell: (row) => row.language.toUpperCase(),
      },
      {
        key: 'isActive',
        header: t('templates.fields.isActive'),
        cell: (row) => <StatusBadge status={row.isActive ? 'active' : 'inactive'} />,
      },
      {
        key: 'actions',
        header: '',
        className: 'w-24',
        cell: (row) => (
          <span className="flex justify-end gap-1">
            <PermissionGuard permission="templates.update">
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

            <PermissionGuard permission="templates.delete">
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
        title={invoiceMode ? t('templates.invoiceTitle') : t('templates.title')}
        description={invoiceMode ? t('templates.invoiceDescription') : t('templates.description')}
        actions={
          <PermissionGuard permission="templates.create">
            <Button size="sm" onClick={() => setCreating(true)}>
              <Plus className="size-4" aria-hidden />
              {invoiceMode ? t('templates.createInvoice') : t('templates.create')}
            </Button>
          </PermissionGuard>
        }
      />

      <TemplateFilterBar
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
        emptyMessage={t('templates.empty')}
      />

      {creating || editing !== null ? (
        <TemplateDialog
          key={editing?.id ?? 'new'}
          template={editing}
          initial={
            editing === null && invoiceMode
              ? {
                  templateType: 'invoice',
                  // `global` est une sentinelle de filtre, pas un client :
                  // l'envoyer comme `customerId` ferait echouer la regle `ulid`.
                  customerId:
                    filters.customerId === undefined || filters.customerId === GLOBAL_SCOPE
                      ? ''
                      : filters.customerId,
                }
              : undefined
          }
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
