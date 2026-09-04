import { Plus } from 'lucide-react'
import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { SearchInput } from '@/shared/components/data/SearchInput'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Button } from '@/shared/components/ui/button'

import { TypeFormDialog, type TypeFormValues } from '../components/TypeFormDialog'
import { TypeItemTable } from '../components/TypeItemTable'
import { TypeSourceList } from '../components/TypeSourceList'
import {
  useCreateTypeItem,
  useCreateTypeSource,
  useDeleteTypeItem,
  useDeleteTypeSource,
  useTypeItems,
  useTypeSources,
  useUpdateTypeItem,
  useUpdateTypeSource,
} from '../hooks/useTypes'
import type { TypeItem, TypeSource } from '../types/type'

type Editing =
  | { kind: 'source'; source: TypeSource }
  | { kind: 'item'; item: TypeItem }
  | { kind: 'newSource' }
  | { kind: 'newItem' }
  | null

/**
 * Les référentiels de type, en un seul écran.
 *
 * Les sources à gauche — véhicule, colis, groupage, et celles que l'organisme
 * ajoute — leurs valeurs à droite. Chaque référentiel avait sa table, son
 * modèle et sa page : en ajouter un demandait du code. Ici, il suffit de le
 * déclarer.
 */
export function TypeListPage() {
  const { t } = useTranslation()

  const [search, setSearch] = useState('')
  const [selected, setSelected] = useState<TypeSource | null>(null)
  const [page, setPage] = useState(1)
  const [editing, setEditing] = useState<Editing>(null)
  const [deletingSource, setDeletingSource] = useState<TypeSource | null>(null)
  const [deletingItem, setDeletingItem] = useState<TypeItem | null>(null)

  const sources = useTypeSources(search === '' ? undefined : search)
  const rows = sources.data?.data ?? []

  // La premiere source est retenue d'office : un ecran ouvert sur un panneau
  // vide laisserait croire qu'il n'y a rien a voir.
  useEffect(() => {
    if (selected === null && rows.length > 0) setSelected(rows[0])
  }, [rows, selected])

  const items = useTypeItems(
    { page, perPage: 25, typeId: selected?.id },
    selected !== null,
  )

  const createSource = useCreateTypeSource()
  const updateSource = useUpdateTypeSource()
  const removeSource = useDeleteTypeSource()
  const createItem = useCreateTypeItem()
  const updateItem = useUpdateTypeItem()
  const removeItem = useDeleteTypeItem()

  const submit = async (values: TypeFormValues) => {
    if (editing === null) return

    if (editing.kind === 'newSource') await createSource.mutateAsync(values)
    else if (editing.kind === 'source') {
      await updateSource.mutateAsync({ id: editing.source.id, ...values })
    } else if (editing.kind === 'newItem') {
      if (selected === null) return
      await createItem.mutateAsync({ typeId: selected.id, ...values })
    } else await updateItem.mutateAsync({ id: editing.item.id, ...values })

    setEditing(null)
  }

  const editedValues =
    editing?.kind === 'source'
      ? editing.source
      : editing?.kind === 'item'
        ? editing.item
        : undefined

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={t('types.title')} description={t('types.subtitle')} />

      <div className="grid gap-6 lg:grid-cols-[minmax(0,20rem)_1fr]">
        <div className="flex flex-col gap-3">
          <SearchInput value={search} onChange={setSearch} />

          <TypeSourceList
            sources={rows}
            selectedId={selected?.id ?? null}
            isLoading={sources.isPending}
            onSelect={(source) => {
              setSelected(source)
              setPage(1)
            }}
            onCreate={() => setEditing({ kind: 'newSource' })}
            onEdit={(source) => setEditing({ kind: 'source', source })}
            onDelete={setDeletingSource}
          />
        </div>

        <SectionCard
          title={selected === null ? t('types.values') : selected.name}
          actions={
            selected === null ? null : (
              <PermissionGuard permission="types.create">
                <Button type="button" size="sm" onClick={() => setEditing({ kind: 'newItem' })}>
                  <Plus className="size-4" aria-hidden />
                  {t('types.createItem')}
                </Button>
              </PermissionGuard>
            )
          }
        >
          {selected === null ? (
            <p className="text-sm text-muted-foreground">{t('types.pickSource')}</p>
          ) : (
            <TypeItemTable
              items={items.data?.data ?? []}
              meta={items.data?.meta}
              isLoading={items.isPending}
              error={items.error}
              onPageChange={setPage}
              onRetry={() => void items.refetch()}
              onEdit={(item) => setEditing({ kind: 'item', item })}
              onDelete={setDeletingItem}
            />
          )}
        </SectionCard>
      </div>

      {editing === null ? null : (
        <TypeFormDialog
          key={editedValues?.id ?? editing.kind}
          open
          onOpenChange={(open) => !open && setEditing(null)}
          title={t(`types.${editing.kind}Title`)}
          description={t('types.formHint')}
          codeLocked={editing.kind === 'source' && editing.source.isSystem}
          defaultValues={
            editedValues === undefined
              ? undefined
              : { code: editedValues.code, name: editedValues.name, status: editedValues.status }
          }
          onSubmit={submit}
        />
      )}

      <ConfirmDialog
        open={deletingSource !== null}
        onOpenChange={(open) => !open && setDeletingSource(null)}
        title={t('confirm.deleteTitle')}
        description={t('confirm.deleteEntity', { name: deletingSource?.name ?? '' })}
        confirmLabel={t('common.delete')}
        isPending={removeSource.isPending}
        onConfirm={() => {
          if (deletingSource === null) return
          removeSource.mutate(deletingSource.id, {
            onSuccess: () => {
              if (selected?.id === deletingSource.id) setSelected(null)
              setDeletingSource(null)
            },
          })
        }}
      />

      <ConfirmDialog
        open={deletingItem !== null}
        onOpenChange={(open) => !open && setDeletingItem(null)}
        title={t('confirm.deleteTitle')}
        description={t('confirm.deleteEntity', { name: deletingItem?.name ?? '' })}
        confirmLabel={t('common.delete')}
        isPending={removeItem.isPending}
        onConfirm={() => {
          if (deletingItem === null) return
          removeItem.mutate(deletingItem.id, { onSuccess: () => setDeletingItem(null) })
        }}
      />
    </div>
  )
}
