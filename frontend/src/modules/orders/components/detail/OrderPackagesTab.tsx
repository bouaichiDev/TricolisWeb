import { CornerDownRight } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import { EmptyState } from '@/shared/components/feedback/EmptyState'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { ListSkeleton } from '@/shared/components/feedback/LoadingSkeleton'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { StatusBadge } from '@/shared/components/data/StatusBadge'

import { usePackageTree } from '../../hooks/useOrders'
import type { OrderLine, OrderPackage, PackageTreeNode } from '../../types/orderDetail'

interface FlatNode {
  node: PackageTreeNode
  depth: number
}

/** Aplatit l'arbre en conservant la profondeur, pour un rendu en liste indentée. */
function flatten(nodes: PackageTreeNode[], depth = 0): FlatNode[] {
  return nodes.flatMap((node) => [{ node, depth }, ...flatten(node.children, depth + 1)])
}

interface OrderPackagesTabProps {
  orderId: string
  packages: OrderPackage[]
  lines: OrderLine[]
}

/**
 * Colis de la commande, dans leur hiérarchie.
 *
 * L'arbre vient de `GET /orders/{order}/packages/tree` : c'est le backend qui
 * l'assemble, le frontend ne reconstruit pas la relation parent-enfant à partir
 * de la liste plate.
 *
 * Le contenu de chaque colis vient de `OrderDetailResource`, déjà chargée : les
 * affectations y figurent avec leur quantité.
 */
export function OrderPackagesTab({ orderId, packages, lines }: OrderPackagesTabProps) {
  const { t } = useTranslation()
  const tree = usePackageTree(orderId)

  const byId = new Map(packages.map((item) => [item.id, item]))
  const lineName = new Map(lines.map((line) => [line.id, line.name]))

  if (tree.isPending) return <ListSkeleton />
  if (tree.error) return <ErrorState error={tree.error} onRetry={() => void tree.refetch()} />

  const nodes = flatten(tree.data ?? [])

  if (nodes.length === 0) {
    return (
      <SectionCard title={t('orders.packages.title')}>
        <EmptyState
          title={t('orders.packages.empty')}
          description={t('orders.wizard.packagesOptional')}
        />
      </SectionCard>
    )
  }

  return (
    <SectionCard title={t('orders.packages.title')}>
      <ul className="flex flex-col gap-3">
        {nodes.map(({ node, depth }) => {
          const detail = byId.get(node.id)

          return (
            <li
              key={node.id}
              style={{ marginLeft: `${depth * 1.5}rem` }}
              className="rounded-md border p-3"
            >
              <div className="flex flex-wrap items-center justify-between gap-2">
                <span className="flex items-center gap-2 font-medium">
                  {depth > 0 ? (
                    <CornerDownRight className="size-4 text-muted-foreground" aria-hidden />
                  ) : null}
                  {node.reference ?? node.barcode ?? node.id}
                </span>
                <StatusBadge status={node.status} />
              </div>

              <p className="mt-1 text-xs text-muted-foreground">
                {detail?.packageType?.name ?? '—'}
                {detail?.groupingType?.name ? ` · ${detail.groupingType.name}` : ''}
                {node.quantity !== null ? ` · ${node.quantity}` : ''}
              </p>

              {detail?.lines && detail.lines.length > 0 ? (
                <ul className="mt-2 flex flex-col gap-1">
                  {detail.lines.map((link) => (
                    <li key={link.id} className="flex justify-between gap-4 text-sm">
                      <span>{lineName.get(link.orderLineId) ?? link.orderLineId}</span>
                      <span className="text-muted-foreground">{String(link.quantity)}</span>
                    </li>
                  ))}
                </ul>
              ) : (
                <p className="mt-2 text-sm text-muted-foreground">{t('orders.packages.noLines')}</p>
              )}
            </li>
          )
        })}
      </ul>
    </SectionCard>
  )
}
