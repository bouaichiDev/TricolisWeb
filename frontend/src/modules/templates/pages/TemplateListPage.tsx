import { Copy, Pencil, Plus, Power, PowerOff, Trash2 } from 'lucide-react'
import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useSearchParams } from 'react-router-dom'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { DataTable } from '@/shared/components/data/DataTable'
import { RowActions } from '@/shared/components/data/RowActions'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { Button } from '@/shared/components/ui/button'

import { TemplateDialog } from '../components/TemplateDialog'
import { TemplateFilterBar } from '../components/TemplateFilterBar'
import { templateColumns } from '../components/templateColumns'
import { GLOBAL_SCOPE, type TemplateFilters } from '../api/templates.api'
import { useDeleteTemplate, useTemplateList, useUpdateTemplate } from '../hooks/useTemplates'
import {
  categoryFromParams,
  soleTypeOf,
  type Template,
  type TemplateCategory,
} from '../types/template'

/**
 * Modèles de l'organisation — messages **et** documents.
 *
 * Un seul écran, une seule table, une seule API. Le menu y mène par deux
 * portes — « Communication › Modèles » et « Facturation › Modèles de facture » —
 * parce qu'un exploitant et un comptable n'y cherchent pas la même chose ; ils
 * arrivent au même endroit, avec un filtre différent.
 *
 * Les modèles de BL n'ont pas de troisième porte : « Modèles » est déjà dans le
 * menu, et une seconde entrée vers le même écran se lit comme un second écran.
 * Leur rayon s'atteint par le filtre, ou par le lien que propose l'écran de
 * génération quand aucun modèle ne s'applique.
 *
 * La catégorie d'arrivée vient de l'URL. Ouvrir la page depuis la facturation
 * ne doit pas obliger à re-sélectionner « facture » pour voir ce qu'on venait
 * voir.
 *
 * `templateType=invoice` reste accepté en plus de `category` : c'est l'ancienne
 * adresse du menu de facturation, et un signet posé dessus ne doit pas ouvrir
 * une page qui ne montre plus rien.
 */
export function TemplateListPage() {
  const { t } = useTranslation()
  const [params] = useSearchParams()

  const arrival = categoryFromParams(params.get('category'), params.get('templateType'))

  const [filters, setFilters] = useState<TemplateFilters>(() => ({
    page: 1,
    perPage: 25,
    category: arrival,
  }))
  const [creating, setCreating] = useState(false)
  const [editing, setEditing] = useState<Template | null>(null)
  const [duplicating, setDuplicating] = useState<Template | null>(null)
  const [deleting, setDeleting] = useState<Template | null>(null)

  const { data, isPending, error, refetch } = useTemplateList(filters)
  const remove = useDeleteTemplate()
  const update = useUpdateTemplate()

  const category = filters.category as TemplateCategory | undefined
  // Le rayon qui ne contient qu'une nature l'impose a la creation : demander
  // « quel type ? » a qui vient de cliquer « Nouveau modèle de BL » serait une
  // question dont l'ecran connait la reponse.
  const soleType = soleTypeOf(category)

  const columns = useMemo(() => templateColumns(t), [t])

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={t(`templates.titles.${category ?? 'all'}`)}
        description={t(`templates.descriptions.${category ?? 'all'}`)}
        actions={
          <PermissionGuard permission="templates.create">
            <Button size="sm" onClick={() => setCreating(true)}>
              <Plus className="size-4" aria-hidden />
              {t(`templates.creates.${category ?? 'all'}`)}
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
        onPerPageChange={(perPage) => setFilters((current) => ({ ...current, perPage, page: 1 }))}
        onRetry={() => void refetch()}
        actions={(row) => (
          <RowActions
            actions={[
              {
                key: 'edit',
                icon: Pencil,
                label: t('common.edit'),
                permission: 'templates.update',
                onClick: () => setEditing(row),
              },
              {
                key: 'duplicate',
                icon: Copy,
                label: t('templates.duplicate'),
                // Dupliquer, c'est creer : c'est ce droit-la qu'il faut, pas
                // celui de modifier le modele qu'on recopie.
                permission: 'templates.create',
                onClick: () => setDuplicating(row),
              },
              {
                key: 'toggle',
                icon: row.isActive ? PowerOff : Power,
                label: row.isActive ? t('templates.deactivate') : t('templates.activate'),
                permission: 'templates.update',
                disabled: update.isPending,
                onClick: () =>
                  update.mutate({ id: row.id, isActive: !row.isActive }),
              },
              {
                key: 'delete',
                icon: Trash2,
                label: t('common.delete'),
                tone: 'danger',
                permission: 'templates.delete',
                onClick: () => setDeleting(row),
              },
            ]}
          />
        )}
        emptyMessage={t('templates.empty')}
      />

      {creating || editing !== null || duplicating !== null ? (
        <TemplateDialog
          key={editing?.id ?? duplicating?.id ?? 'new'}
          template={editing}
          duplicateOf={duplicating}
          initial={
            editing === null && duplicating === null && soleType !== undefined
              ? {
                  templateType: soleType,
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
            setDuplicating(null)
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
