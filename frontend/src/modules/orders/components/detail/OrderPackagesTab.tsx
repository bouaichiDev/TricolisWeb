import { Plus } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { EmptyState } from '@/shared/components/feedback/EmptyState'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { ListSkeleton } from '@/shared/components/feedback/LoadingSkeleton'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Button } from '@/shared/components/ui/button'

import { useDeleteOrderPackage } from '../../hooks/useOrderContent'
import { usePackageTree } from '../../hooks/useOrders'
import { orderLineUsage } from '../../schemas/orderAllocations'
import type { OrderLine, OrderPackage, PackageTreeNode } from '../../types/orderDetail'
import { OrderPackageDialog } from './OrderPackageDialog'
import { PackageNodeCard } from './PackageNodeCard'
import { packageDisplayName } from './packageParents'

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
  const parentName = new Map(packages.map((item) => [item.id, packageDisplayName(item)]))
  const usage = orderLineUsage(lines, packages)

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
              <PackageNodeCard
                key={node.id}
                orderId={orderId}
                node={node}
                depth={depth}
                detail={detail}
                parentLabel={
                  detail?.parentPackageId ? parentName.get(detail.parentPackageId) : undefined
                }
                lines={lines}
                usage={usage}
                editable={editable}
                onEdit={setEditing}
                onDelete={setDeleting}
              />
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
