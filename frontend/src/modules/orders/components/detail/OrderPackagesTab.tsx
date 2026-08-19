import { CornerDownRight, Pencil, Plus, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { EmptyState } from '@/shared/components/feedback/EmptyState'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { ListSkeleton } from '@/shared/components/feedback/LoadingSkeleton'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Button } from '@/shared/components/ui/button'

import { useDeleteOrderPackage } from '../../hooks/useOrderContent'
import { usePackageTree } from '../../hooks/useOrders'
import type { OrderLine, OrderPackage, PackageTreeNode } from '../../types/orderDetail'
import { EntityHistory } from './EntityHistory'
import { OrderPackageDialog } from './OrderPackageDialog'

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
  editable: boolean
}

/**
 * Colis de la commande, dans leur hiérarchie.
 *
 * L'arbre vient de `GET /orders/{order}/packages/tree` : c'est le backend qui
 * l'assemble, le frontend ne reconstruit pas la relation parent-enfant à partir
 * de la liste plate.
 *
 * Le contenu de chaque colis vient d'`OrderDetailResource`, déjà chargée : les
 * affectations y figurent avec leur quantité.
 */
export function OrderPackagesTab({ orderId, packages, lines, editable }: OrderPackagesTabProps) {
  const { t } = useTranslation()
  const tree = usePackageTree(orderId)
  const remove = useDeleteOrderPackage(orderId)

  const [editing, setEditing] = useState<OrderPackage | null>(null)
  const [creating, setCreating] = useState(false)
  const [deleting, setDeleting] = useState<OrderPackage | null>(null)

  const byId = new Map(packages.map((item) => [item.id, item]))
  const lineName = new Map(lines.map((line) => [line.id, line.name]))

  const addAction = editable ? (
    <PermissionGuard permission="packages.create">
      <Button type="button" variant="outline" size="sm" onClick={() => setCreating(true)}>
        <Plus className="size-4" aria-hidden />
        {t('orders.packages.add')}
      </Button>
    </PermissionGuard>
  ) : null

  if (tree.isPending) return <ListSkeleton />
  if (tree.error) return <ErrorState error={tree.error} onRetry={() => void tree.refetch()} />

  const nodes = flatten(tree.data ?? [])

  return (
    <SectionCard title={t('orders.packages.title')} actions={addAction}>
      {nodes.length === 0 ? (
        <EmptyState
          title={t('orders.packages.empty')}
          description={t('orders.wizard.packagesOptional')}
        />
      ) : (
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

                  <div className="flex items-center gap-1">
                    <StatusBadge status={node.status} />

                    {editable && detail ? (
                      <>
                        <PermissionGuard permission="packages.update">
                          <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => setEditing(detail)}
                            aria-label={t('orders.packages.edit')}
                          >
                            <Pencil className="size-4" aria-hidden />
                          </Button>
                        </PermissionGuard>

                        <PermissionGuard permission="packages.delete">
                          <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => setDeleting(detail)}
                            aria-label={t('orders.packages.remove')}
                          >
                            <Trash2 className="size-4" aria-hidden />
                          </Button>
                        </PermissionGuard>
                      </>
                    ) : null}
                  </div>
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
                  <p className="mt-2 text-sm text-muted-foreground">
                    {t('orders.packages.noLines')}
                  </p>
                )}

                <div className="mt-2 border-t pt-2">
                  <EntityHistory entityType="package" entityId={node.id} />
                </div>
              </li>
            )
          })}
        </ul>
      )}

      <OrderPackageDialog
        key={editing?.id ?? 'new'}
        orderId={orderId}
        pkg={editing}
        packages={packages}
        open={editing !== null || creating}
        onOpenChange={(open) => {
          if (!open) {
            setEditing(null)
            setCreating(false)
          }
        }}
      />

      <ConfirmDialog
        open={deleting !== null}
        onOpenChange={(open) => !open && setDeleting(null)}
        title={t('orders.packages.remove')}
        description={t('orders.packages.deleteConfirm')}
        confirmLabel={t('common.delete')}
        isPending={remove.isPending}
        onConfirm={() => {
          if (deleting === null) return
          remove.mutate(deleting.id, { onSuccess: () => setDeleting(null) })
        }}
      />
    </SectionCard>
  )
}
