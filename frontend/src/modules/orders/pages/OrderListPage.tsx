import { Eye, Pencil, Plus } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useSearchParams } from 'react-router-dom'

import { OrderFilterBar } from '../components/OrderFilters'
import { OrderSourceBadge, OrderStatusBadge } from '../components/OrderStatusBadge'
import { useOrderList } from '../hooks/useOrders'
import { ORDER_SORTABLE, type OrderFilters, type OrderListItem } from '../types/order'
import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { RowActions } from '@/shared/components/data/RowActions'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { Button } from '@/shared/components/ui/button'
import { formatDate } from '@/shared/utils/format'

const INITIAL: OrderFilters = { page: 1, perPage: 25, sort: 'order_date', direction: 'desc' }

/** Filtres que l'URL peut porter, et qu'aucune autre valeur ne peut prendre. */
const FROM_URL = ['status', 'source', 'createdFrom', 'createdTo', 'search'] as const

/**
 * Les filtres d'arrivée, lus **une fois** sur l'URL.
 *
 * C'est ainsi qu'une carte du tableau de bord ouvre ce qu'elle montre : cliquer
 * la colonne du 3 septembre mène ici avec `createdFrom` et `createdTo` sur ce
 * jour-là. Sans cette lecture, le lien arrivait sur la liste entière — la carte
 * tenait sa promesse à la lettre, et pas celle que le geste avait faite.
 *
 * Les valeurs ne sont **pas** validées ici : le serveur refuse en 422 un statut
 * qui n'existe pas, et le redire au navigateur donnerait deux listes de statuts
 * à tenir d'accord. Ce qui est lu est seulement ce que la liste sait envoyer.
 */
function initialFilters(params: URLSearchParams): OrderFilters {
  const filters: OrderFilters = { ...INITIAL }

  for (const key of FROM_URL) {
    const value = params.get(key)

    if (value !== null && value !== '') filters[key] = value
  }

  return filters
}

/**
 * Liste des commandes — page centrale de l'exploitation.
 *
 * Le tri est borné à ce que `OrderListQuery` accepte : `order_number`,
 * `order_date`, `status`, `created_at`. Envoyer une autre colonne renvoie 422.
 *
 * Les compteurs de lignes et de services viennent de `withCount` côté serveur :
 * aucune requête supplémentaire n'est faite par ligne du tableau.
 *
 * **La ligne n'est plus cliquable.** Elle menait à la fiche, ce qui rendait son
 * texte insélectionnable : copier un numéro de commande ouvrait l'écran au lieu
 * de copier quoi que ce soit. La colonne d'actions le dit maintenant en toutes
 * lettres, et le numéro reste un lien pour qui veut y aller d'un clic.
 *
 * **Les filtres d'arrivée viennent de l'URL.** C'est ce qui donne son sens au
 * clic sur une part de graphe du tableau de bord : la colonne d'un jour ouvre
 * les commandes de ce jour, la part d'un statut ouvre celles de ce statut. Ils
 * s'affichent dans la barre, où l'on peut les élargir ou les retirer — un
 * filtre invisible aurait fait passer une liste tronquée pour la liste entière.
 */
export function OrderListPage() {
  const { t } = useTranslation()

  const [params] = useSearchParams()

  // Lus à la construction, puis oubliés : ce qui suit se règle dans la barre de
  // filtres, et réécrire l'URL à chaque frappe aurait empilé un historique dont
  // on ne sort plus qu'en maintenant le bouton « retour ».
  const [filters, setFilters] = useState<OrderFilters>(() => initialFilters(params))

  const { data, isPending, error, refetch } = useOrderList(filters)

  const columns: Column<OrderListItem>[] = [
    {
      key: 'orderNumber',
      header: t('orders.fields.orderNumber'),
      sortKey: 'order_number',
      cell: (row) => (
        <Link
          to={`/orders/${row.id}`}
          className="font-medium text-primary hover:underline"
          onClick={(event) => event.stopPropagation()}
        >
          {row.orderNumber}
        </Link>
      ),
    },
    {
      key: 'customer',
      header: t('orders.fields.customer'),
      cell: (row) => row.customerName ?? <span className="text-muted-foreground">—</span>,
    },
    {
      key: 'agency',
      header: t('orders.fields.agency'),
      hideOnMobile: true,
      cell: (row) => row.agencyName ?? <span className="text-muted-foreground">—</span>,
    },
    {
      key: 'orderDate',
      header: t('orders.fields.orderDate'),
      sortKey: 'order_date',
      cell: (row) => formatDate(row.orderDate),
    },
    {
      key: 'content',
      header: t('orders.fields.content'),
      hideOnMobile: true,
      cell: (row) => (
        <span className="text-sm text-muted-foreground">
          {t('orders.lineCount', { count: row.lineCount })} ·{' '}
          {t('orders.serviceCount', { count: row.serviceCount })}
        </span>
      ),
    },
    {
      key: 'source',
      header: t('orders.fields.source'),
      hideOnMobile: true,
      cell: (row) => <OrderSourceBadge source={row.source} />,
    },
    {
      key: 'status',
      header: t('orders.fields.status'),
      sortKey: 'status',
      cell: (row) => <OrderStatusBadge status={row.status} label={row.statusLabel} />,
    },
  ]

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={t('orders.title')}
        description={t('orders.subtitle')}
        actions={
          <PermissionGuard permission="orders.create">
            <Button asChild>
              <Link to="/orders/create">
                <Plus className="size-4" aria-hidden />
                {t('orders.create')}
              </Link>
            </Button>
          </PermissionGuard>
        }
      />

      <OrderFilterBar
        filters={filters}
        onChange={(patch) => setFilters((current) => ({ ...current, ...patch, page: 1 }))}
        onReset={() => setFilters(INITIAL)}
      />

      <DataTable
        columns={columns}
        rows={data?.data ?? []}
        rowKey={(row) => row.id}
        meta={data?.meta}
        isLoading={isPending}
        error={error}
        sort={filters.sort}
        direction={filters.direction}
        onSortChange={(sortKey) => {
          if (!ORDER_SORTABLE.includes(sortKey as (typeof ORDER_SORTABLE)[number])) return

          setFilters((current) => ({
            ...current,
            sort: sortKey,
            direction: current.sort === sortKey && current.direction === 'asc' ? 'desc' : 'asc',
          }))
        }}
        onPageChange={(page) => setFilters((current) => ({ ...current, page }))}
        onPerPageChange={(perPage) => setFilters((current) => ({ ...current, perPage, page: 1 }))}
        onRetry={() => void refetch()}
        actions={(row) => (
          <RowActions
            actions={[
              {
                key: 'view',
                icon: Eye,
                label: t('common.view'),
                to: `/orders/${row.id}`,
              },
              {
                key: 'edit',
                icon: Pencil,
                label: t('common.edit'),
                to: `/orders/${row.id}/edit`,
                permission: 'orders.update',
              },
            ]}
          />
        )}
        emptyMessage={t('orders.empty')}
      />
    </div>
  )
}
