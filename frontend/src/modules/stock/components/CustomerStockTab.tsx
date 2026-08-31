import { Plus } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Button } from '@/shared/components/ui/button'
import { formatDateTime } from '@/shared/utils/format'

import { StockKpiRow } from './StockKpiRow'
import { useCustomerStockBalances } from '../hooks/useStockBalances'
import { useCustomerStockItems } from '../hooks/useStockItems'
import type { StockBalance, StockItem } from '../types/stock'
import { formatStockQuantity, STOCK_ITEM_SOURCE } from '../utils/stockSources'

/**
 * Le stock d'un client, sur sa fiche.
 *
 * Le stock est physiquement celui du transporteur, mais reste séparé par
 * client : c'est ici que la séparation se voit. Deux routes dédiées le servent,
 * `customers/{c}/stock-items` et `customers/{c}/stock-balances`, ce qui évite de
 * filtrer une liste globale côté écran.
 *
 * Les mouvements et les réservations ne sont pas repris ici : aucune route ne
 * les filtre par client — ni `ListStockMovementRequest` ni
 * `ListStockReservationRequest` n'accepte `customerId`. Les afficher demanderait
 * de charger l'ensemble puis de trier en mémoire, ce qui donnerait une liste
 * fausse dès la deuxième page. Les liens mènent aux écrans qui savent le faire.
 */
export function CustomerStockTab({ customerId }: { customerId: string }) {
  const { t } = useTranslation()

  const items = useCustomerStockItems(customerId, { page: 1, perPage: 25 })
  const balances = useCustomerStockBalances(customerId, {
    page: 1,
    perPage: 100,
    customerId,
    sort: 'available_quantity',
    direction: 'desc',
  })

  const balanceRows = balances.data?.data ?? []
  const balanceTotal = balances.data?.meta.total ?? balanceRows.length

  const itemColumns: Column<StockItem>[] = [
    {
      key: 'articleCode',
      header: t('stock.fields.articleCode'),
      cell: (row) => (
        <Link to={`/stock/items/${row.id}`} className="font-medium hover:underline">
          {row.articleCode}
        </Link>
      ),
    },
    {
      key: 'description',
      header: t('stock.fields.description'),
      hideOnMobile: true,
      cell: (row) => row.description ?? '—',
    },
    {
      key: 'status',
      header: t('stock.fields.status'),
      cell: (row) => <StatusBadge status={row.status} source={STOCK_ITEM_SOURCE} />,
    },
  ]

  const balanceColumns: Column<StockBalance>[] = [
    {
      key: 'article',
      header: t('stock.fields.articleCode'),
      cell: (row) => row.articleCode ?? row.stockItemId,
    },
    {
      key: 'location',
      header: t('stock.fields.location'),
      cell: (row) => row.locationCode ?? row.stockLocationId,
    },
    {
      key: 'quantity',
      header: t('stock.fields.quantity'),
      cell: (row) => formatStockQuantity(row.quantity),
    },
    {
      key: 'availableQuantity',
      header: t('stock.fields.availableQuantity'),
      cell: (row) => (
        <span className="font-medium">{formatStockQuantity(row.availableQuantity)}</span>
      ),
    },
    {
      key: 'updatedAt',
      header: t('stock.fields.updatedAt'),
      hideOnMobile: true,
      cell: (row) => formatDateTime(row.updatedAt),
    },
  ]

  return (
    <div className="flex flex-col gap-6">
      <StockKpiRow
        balances={balanceRows}
        isLoading={balances.isPending}
        isPartial={balanceTotal > balanceRows.length}
        total={balanceTotal}
      />

      <SectionCard
        title={t('stock.items')}
        description={t('stock.customerItemsHint')}
        actions={
          <PermissionGuard permission="stock_items.create">
            <Button variant="outline" size="sm" asChild>
              <Link to={`/stock/items/create?customerId=${customerId}`}>
                <Plus className="size-4" aria-hidden />
                {t('stock.newItem')}
              </Link>
            </Button>
          </PermissionGuard>
        }
      >
        <DataTable
          columns={itemColumns}
          rows={items.data?.data ?? []}
          rowKey={(row) => row.id}
          isLoading={items.isPending}
          error={items.error}
          onRetry={() => void items.refetch()}
          emptyMessage={t('stock.noItemForCustomer')}
        />
      </SectionCard>

      <SectionCard title={t('stock.balances')} description={t('stock.customerBalancesHint')}>
        <DataTable
          columns={balanceColumns}
          rows={balanceRows}
          rowKey={(row) => row.id}
          isLoading={balances.isPending}
          error={balances.error}
          onRetry={() => void balances.refetch()}
          emptyMessage={t('stock.noBalance')}
        />
      </SectionCard>

      <SectionCard title={t('stock.elsewhere')} description={t('stock.elsewhereHint')}>
        <ul className="flex flex-wrap gap-2">
          <li>
            <Link
              to="/stock/movements"
              className="rounded-md border px-3 py-1.5 text-sm hover:bg-muted"
            >
              {t('stock.movementsTitle')}
            </Link>
          </li>
          <li>
            <Link
              to="/stock/reservations"
              className="rounded-md border px-3 py-1.5 text-sm hover:bg-muted"
            >
              {t('stock.reservations')}
            </Link>
          </li>
        </ul>
      </SectionCard>
    </div>
  )
}
