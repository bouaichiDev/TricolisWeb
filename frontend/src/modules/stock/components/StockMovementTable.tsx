import { ArrowDownToLine, ArrowUpFromLine, MoveRight } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { formatDateTime } from '@/shared/utils/format'

import { useStockMovements } from '../hooks/useStock'
import { useStockLocationOptions } from '../hooks/useStockScope'
import { movementDirection, type StockMovement } from '../types/stock'

const ICONS = {
  entry: ArrowDownToLine,
  exit: ArrowUpFromLine,
  transfer: MoveRight,
}

/**
 * Historique des mouvements d'un article.
 *
 * C'est l'écriture qui fait foi : un solde se recalcule, un mouvement ne se
 * modifie pas. La route `stock-movements` n'expose d'ailleurs que
 * `index`, `store` et `show` — ni `update` ni `destroy`, et c'est délibéré.
 *
 * Le sens n'est pas lu dans `movementType`, qui est une chaîne libre : il se
 * déduit des emplacements, comme le fait `CreateStockMovementAction`.
 */
export function StockMovementTable({ stockItemId }: { stockItemId: string }) {
  const { t } = useTranslation()
  const [page, setPage] = useState(1)
  const locations = useStockLocationOptions()

  const { data, isPending, error, refetch } = useStockMovements(
    { page, perPage: 25, stockItemId },
    stockItemId !== '',
  )

  const label = (id: string | null) =>
    id === null
      ? '—'
      : (locations.options.find((option) => option.value === id)?.label ?? id)

  const columns: Column<StockMovement>[] = [
    {
      key: 'createdAt',
      header: t('stock.fields.createdAt'),
      cell: (row) => (row.createdAt === null ? '—' : formatDateTime(row.createdAt)),
    },
    {
      key: 'direction',
      header: t('stock.fields.direction'),
      cell: (row) => {
        const direction = movementDirection(row)
        const Icon = ICONS[direction]

        return (
          <span className="flex items-center gap-1.5">
            <Icon className="size-4 text-muted-foreground" aria-hidden />
            {t(`stock.directions.${direction}`)}
          </span>
        )
      },
    },
    {
      key: 'movementType',
      header: t('stock.fields.movementType'),
      hideOnMobile: true,
      cell: (row) => row.movementType,
    },
    {
      key: 'route',
      header: t('stock.fields.route'),
      hideOnMobile: true,
      cell: (row) => (
        <span className="whitespace-nowrap text-sm">
          {label(row.sourceLocationId)} → {label(row.destinationLocationId)}
        </span>
      ),
    },
    {
      key: 'quantity',
      header: t('stock.fields.quantity'),
      cell: (row) => <span className="font-medium">{String(row.quantity)}</span>,
    },
  ]

  return (
    <DataTable
      columns={columns}
      rows={data?.data ?? []}
      rowKey={(row) => row.id}
      meta={data?.meta}
      isLoading={isPending}
      error={error}
      onPageChange={setPage}
      onRetry={() => void refetch()}
      emptyMessage={t('stock.noMovement')}
    />
  )
}
