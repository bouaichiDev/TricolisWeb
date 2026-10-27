import { Eye, Pencil, Plus, Power, PowerOff } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router-dom'

import { Can } from '@/app/guards/Can'
import { paths } from '@/app/router/paths'
import { errorMessage } from '@/shared/api/errors'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { RowActions } from '@/shared/components/data/RowActions'
import { SearchInput } from '@/shared/components/data/SearchInput'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { Badge } from '@/shared/components/ui/badge'
import { Button } from '@/shared/components/ui/button'

import { CatalogItemDialog } from '../components/CatalogItemDialog'
import { useCatalogItems, useCatalogs } from '../hooks/useCatalog'
import { useCatalogItemToggle } from '../hooks/useCatalogItemToggle'
import type { CatalogFilters, CatalogItem } from '../types'

const columns: Column<CatalogItem>[] = [
  {
    key: 'articleCode',
    header: 'Code',
    sortKey: 'articleCode',
    cell: (item) => <Link to={paths.catalogItem(item.id)} className="font-medium text-primary hover:underline">{item.articleCode}</Link>,
  },
  { key: 'name', header: 'Désignation', sortKey: 'name', cell: (item) => item.name },
  { key: 'barcode', header: 'Code-barres', hideOnMobile: true, cell: (item) => item.barcode ?? '—' },
  { key: 'catalog', header: 'Catalogue', hideOnMobile: true, cell: (item) => item.catalog?.name ?? '—' },
  { key: 'status', header: 'Statut', cell: (item) => (item.isActive ? <Badge variant="outline">Actif</Badge> : <Badge variant="secondary">Inactif</Badge>) },
]

/** Le catalogue du client : les articles qu'il fait transporter. */
export function CatalogPage() {
  const [filters, setFilters] = useState<CatalogFilters>({ page: 1 })
  const [adding, setAdding] = useState(false)
  const [editing, setEditing] = useState<CatalogItem | null>(null)
  const { toggle } = useCatalogItemToggle()
  const catalogs = useCatalogs()
  const items = useCatalogItems(filters)
  const update = (changes: CatalogFilters) => setFilters((current) => ({ ...current, page: 1, ...changes }))

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title="Catalogue"
        description="Les articles que vous faites transporter, utilisables dans vos commandes et votre stock."
        actions={
          <Can permission="customer.articles.create">
            <Button onClick={() => setAdding(true)} disabled={!catalogs.data?.some((catalog) => catalog.isActive)}>
              <Plus className="size-4" aria-hidden /> Ajouter un article
            </Button>
          </Can>
        }
      />

      <div className="flex flex-wrap items-center gap-3">
        <div className="w-full sm:w-72">
          <SearchInput value={filters.search ?? ''} onChange={(search) => update({ search: search || undefined })} placeholder="Code, code-barres, désignation…" />
        </div>
        {(catalogs.data?.length ?? 0) > 1 && (
          <select
            aria-label="Catalogue"
            className="h-9 rounded-md border bg-background px-2 text-sm"
            value={filters.catalogId ?? ''}
            onChange={(event) => update({ catalogId: event.target.value || undefined })}
          >
            <option value="">Tous les catalogues</option>
            {catalogs.data?.map((catalog) => <option key={catalog.id} value={catalog.id}>{catalog.name}</option>)}
          </select>
        )}
        <select
          aria-label="Statut"
          className="h-9 rounded-md border bg-background px-2 text-sm"
          value={filters.status ?? ''}
          onChange={(event) => update({ status: (event.target.value || undefined) as CatalogFilters['status'] })}
        >
          <option value="">Tous les statuts</option>
          <option value="active">Actifs</option>
          <option value="inactive">Inactifs</option>
        </select>
      </div>

      <DataTable
        columns={columns}
        rows={items.data?.data ?? []}
        rowKey={(item) => item.id}
        meta={items.data?.meta}
        isLoading={items.isPending}
        errorMessage={items.isError ? errorMessage(items.error) : null}
        onRetry={() => void items.refetch()}
        sort={filters.sort ?? 'articleCode'}
        direction={filters.direction ?? 'asc'}
        onSortChange={(sortKey) => update({ sort: sortKey as CatalogFilters['sort'], direction: filters.sort === sortKey && filters.direction !== 'desc' ? 'desc' : 'asc' })}
        onPageChange={(page) => setFilters((current) => ({ ...current, page }))}
        onPerPageChange={(perPage) => setFilters((current) => ({ ...current, perPage, page: 1 }))}
        actions={(item) => (
          <RowActions
            actions={[
              { key: 'view', icon: Eye, label: 'Voir', to: paths.catalogItem(item.id) },
              ...(item.actions?.canUpdate
                ? [
                    { key: 'edit', icon: Pencil, label: 'Modifier', onClick: () => setEditing(item) },
                    item.isActive
                      ? { key: 'off', icon: PowerOff, label: 'Désactiver', tone: 'danger' as const, onClick: () => void toggle(item) }
                      : { key: 'on', icon: Power, label: 'Réactiver', onClick: () => void toggle(item) },
                  ]
                : []),
            ]}
          />
        )}
        emptyMessage={catalogs.data?.length === 0 ? 'Votre transporteur ne vous a pas encore ouvert de catalogue.' : 'Aucun article ne correspond.'}
      />

      <CatalogItemDialog catalogs={catalogs.data ?? []} open={adding} onOpenChange={setAdding} />
      {editing && <CatalogItemDialog catalogs={[]} item={editing} open onOpenChange={(open) => !open && setEditing(null)} />}
    </div>
  )
}
